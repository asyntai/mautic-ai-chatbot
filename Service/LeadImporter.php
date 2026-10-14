<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Service;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadNote;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\NoteModel;

/**
 * Turns Asyntai chat leads into Mautic contacts.
 *
 * Each chat where a visitor left an email address or a phone number becomes a
 * contact (or updates the contact that has that address), gets the plugin's
 * tag, and gets a note with the page of the chat and a link to read it.
 *
 * A visitor can leave an address some minutes after the chat starts, so every
 * run reads back two days before the newest chat it saw. Chats that are
 * already imported are skipped by their session ID.
 */
class LeadImporter
{
    private const PAGE_SIZE = 100;

    private const OVERLAP = 'P2D';

    public function __construct(
        private readonly Settings $settings,
        private readonly AsyntaiClient $client,
        private readonly LeadModel $leadModel,
        private readonly NoteModel $noteModel,
    ) {
    }

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int, errors: string[]}
     *
     * @throws \RuntimeException when Asyntai cannot be read; nothing is saved then
     */
    public function run(int $maxPages = 50): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $apiKey = $this->settings->apiKey();
        if ('' === $apiKey) {
            throw new \RuntimeException('Add your Asyntai API key first.');
        }

        $cursor = $this->parse((string) $this->settings->get('cursor', ''));
        $since  = $cursor ? $cursor->sub(new \DateInterval(self::OVERLAP))->format(\DateTimeInterface::ATOM) : null;
        $seen   = $this->settings->seen();
        $known  = array_flip($seen);
        $newest = $cursor;

        $fresh  = [];
        $before = null;
        for ($page = 0; $page < $maxPages; ++$page) {
            $leads = $this->client->leads($apiKey, $since, $before, self::PAGE_SIZE);
            foreach ($leads as $lead) {
                $started = $this->parse((string) ($lead['started_at'] ?? ''));
                if ($started && (!$newest || $started > $newest)) {
                    $newest = $started;
                }
                $sessionId = (string) ($lead['session_id'] ?? '');
                if ('' === $sessionId || isset($known[$sessionId]) || isset($fresh[$sessionId])) {
                    continue;
                }
                $fresh[$sessionId] = $lead;
            }
            if (count($leads) < self::PAGE_SIZE) {
                break;
            }
            $last = (string) ($leads[count($leads) - 1]['started_at'] ?? '');
            if ('' === $last || $last === $before) {
                break;
            }
            $before = $last;
        }

        // Oldest first, so the contact notes read in the order of the chats.
        foreach (array_reverse($fresh, true) as $sessionId => $lead) {
            try {
                $outcome = $this->importOne($lead);
                ++$result[$outcome];
                $seen[] = (string) $sessionId;
            } catch (\Throwable $e) {
                ++$result['failed'];
                if (count($result['errors']) < 5) {
                    $result['errors'][] = $sessionId.': '.$e->getMessage();
                }
            }
        }

        $this->settings->save([
            'cursor'      => $newest ? $newest->format(\DateTimeInterface::ATOM) : '',
            'seen'        => $this->settings->trimSeen($seen),
            'last_import' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            'last_result' => ['created' => $result['created'], 'updated' => $result['updated'], 'failed' => $result['failed']],
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $lead
     *
     * @return 'created'|'updated'|'skipped'
     */
    public function importOne(array $lead): string
    {
        $email = strtolower(trim((string) ($lead['email'] ?? '')));
        if ('' !== $email && false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }
        $phone = trim((string) ($lead['phone'] ?? ''));
        if (!preg_match('/\d{5}/', preg_replace('/\D+/', '', $phone) ?? '')) {
            $phone = '';
        }
        if ('' === $email && '' === $phone) {
            return 'skipped';
        }

        $contact = $this->find($email, $phone);
        $created = null === $contact;
        if ($created) {
            $contact = new Lead();
            $contact->setNewlyCreated(true);
        }

        $data = [];
        if ('' !== $email && '' === trim((string) $contact->getEmail())) {
            $data['email'] = $email;
        }
        if ('' !== $phone && '' === trim((string) $contact->getFieldValue('phone'))) {
            $data['phone'] = $phone;
        }
        if ($data) {
            $this->leadModel->setFieldValues($contact, $data, false, false);
        }
        $this->leadModel->saveEntity($contact);
        $this->leadModel->modifyTags($contact, [$this->settings->tag()]);
        $this->addNote($contact, $lead);

        return $created ? 'created' : 'updated';
    }

    private function find(string $email, string $phone): ?Lead
    {
        $repository = $this->leadModel->getRepository();
        if ('' !== $email) {
            $found = $repository->getContactsByEmail($email);
            if (!empty($found)) {
                return reset($found);
            }
        }
        if ('' !== $phone) {
            $found = $repository->getLeadsByFieldValue('phone', $phone);
            if (!empty($found)) {
                return reset($found);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $lead
     */
    private function addNote(Lead $contact, array $lead): void
    {
        $sessionId = (string) ($lead['session_id'] ?? '');
        $chatUrl   = $this->settings->origin().'/chat/'.rawurlencode($sessionId).'/';
        $pageUrl   = trim((string) ($lead['page_url'] ?? ''));

        $text = 'Chat with the Asyntai AI Chatbot';
        if ('' !== $pageUrl && preg_match('#^https?://#i', $pageUrl)) {
            $text .= ' on <a href="'.$this->e($pageUrl).'" target="_blank" rel="noopener">'.$this->e($pageUrl).'</a>';
        }
        $text .= '. <a href="'.$this->e($chatUrl).'" target="_blank" rel="noopener">Read the chat</a>';

        $note = new LeadNote();
        $note->setLead($contact);
        $note->setType('general');
        $note->setText($text);
        $started = $this->parse((string) ($lead['started_at'] ?? ''));
        $note->setDateTime(\DateTime::createFromImmutable($started ?: new \DateTimeImmutable()));
        $this->noteModel->saveEntity($note);
    }

    private function parse(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
