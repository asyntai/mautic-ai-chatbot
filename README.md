# Asyntai AI Chatbot for Mautic

An AI chatbot on your Mautic landing pages. It answers your visitors around the clock, in their language, from the content of your website. When a visitor leaves an email address or a phone number in the chat, the plugin turns it into a Mautic contact, with a tag for your segments and campaigns and a link to the whole chat.

[Asyntai](https://asyntai.com) · [Documentation](https://asyntai.com/documentation/integrations/mautic/)

## What it does

- **AI chatbot on your landing pages.** Connect Mautic to an Asyntai account with one button. The chat shows on every landing page, or only on the pages where you put the `{asyntaichat}` token.
- **Chat leads become Mautic contacts.** Every email address and phone number from the chat becomes a contact, or updates the contact that already has that address. The contact gets your tag (default `asyntai-chat`) and a note with the page of the chat and a link to read it.
- **Segments and campaigns.** Build a segment on the tag and start a campaign for every new chat lead.
- **Any language.** Visitors write in their own language and the chatbot answers in it.

## Requirements

- Mautic 6 or 7 (tested on 6.0.9 and 7.2.1)
- An Asyntai account. The contact import uses an Asyntai API key, which comes with the Starter plan or higher.

## Installation

From the Mautic Marketplace: open **Settings → Marketplace**, find **Asyntai AI Chatbot** and press **Install**.

With Composer, in your Mautic folder:

```
composer require asyntai/mautic-ai-chatbot
php bin/console cache:clear
php bin/console mautic:plugins:reload
```

By hand: put this folder into the `plugins` folder of your Mautic as `plugins/AsyntaiBundle`, clear the cache, then open **Settings → Plugins** and press **Install/Upgrade Plugins**.

## Setup

1. **Connect.** Open **Settings → Asyntai AI Chatbot** and press **Get started**. A window opens where you sign in to Asyntai, or create a free account, and enter your website address. The chatbot learns from your website. When the window closes, the chat is live on your landing pages.
2. **Where the chat shows.** Choose every landing page, only the pages with the `{asyntaichat}` token, or nowhere. The token is in the landing page builder under **Asyntai AI Chatbot**.
3. **Contacts.** Tick **Import chat leads as contacts**, paste an API key from the Asyntai panel (**Settings → API**), set the tag and press **Save**. Press **Import now** for a first import.
4. **Cron.** Add the import to your Mautic cron jobs, for example every 15 minutes:

```
*/15 * * * * php /path/to/mautic/bin/console mautic:asyntai:import-leads
```

The **Published** switch on the Plugins page, and the **Switched on** box on the settings page, turn the chat and the import on and off.

## Uninstall

Open **Settings → Plugins**, switch the plugin off, then remove the `plugins/AsyntaiBundle` folder (or `composer remove asyntai/mautic-ai-chatbot`) and clear the cache.

## Support

hello@asyntai.com

## License

MIT
