<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Command;

use MauticPlugin\AsyntaiBundle\Service\LeadImporter;
use MauticPlugin\AsyntaiBundle\Service\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Imports new Asyntai chat leads as Mautic contacts. Add it to the Mautic
 * cron jobs, for example every 15 minutes:
 *
 *     php bin/console mautic:asyntai:import-leads
 */
#[AsCommand(
    name: 'mautic:asyntai:import-leads',
    description: 'Import the email addresses and phone numbers that visitors left in Asyntai AI Chatbot chats as Mautic contacts.'
)]
class ImportLeadsCommand extends Command
{
    public function __construct(
        private readonly Settings $settings,
        private readonly LeadImporter $importer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->settings->isPublished()) {
            $output->writeln('The Asyntai AI Chatbot plugin is not published. Nothing to do.');

            return Command::SUCCESS;
        }
        if (!$this->settings->importEnabled()) {
            $output->writeln('The contact import is off. Switch it on in Settings > Asyntai AI Chatbot.');

            return Command::SUCCESS;
        }

        try {
            $result = $this->importer->run();
        } catch (\RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Asyntai leads: %d new contacts, %d updated contacts, %d skipped, %d failed.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['failed']
        ));
        foreach ($result['errors'] as $error) {
            $output->writeln('<comment>'.$error.'</comment>');
        }

        return $result['failed'] > 0 && 0 === $result['created'] + $result['updated'] ? Command::FAILURE : Command::SUCCESS;
    }
}
