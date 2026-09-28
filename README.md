# FeatValue for Contao

Embeds the [FeatValue](https://www.featvalue.com/contao) client portal in a Contao site.

FeatValue is the client portal for agencies and service providers. Your team keeps working in the tools you already use (Jira, Asana, ClickUp, Trello, Basecamp and others). Clients follow tasks, leave feedback and approve work on your website.

## Requirements

- Contao 5.3 or newer, PHP 8.1 or later with the sodium extension
- A [FeatValue account](https://www.featvalue.com/contao) (a free plan is available)

Nothing is sent to FeatValue until an administrator clicks Connect.

## Installation

From the Contao project root:

```
composer require featvalue/contao
vendor/bin/contao-console contao:migrate
```

Or search for "featvalue" in the Contao Manager. Then open **System → FeatValue** and click **Connect with FeatValue**.

## Configuration

- Content element **FeatValue dashboard** and front end module **FeatValue dashboard**, each with a height setting.
- After setup, **Tasks** is available under **System → Tasks**.
- Optional: sign-in with Contao accounts (back end users and members stay separate), and a mail relay every five minutes. Mail uses the Symfony mailer configured in Contao.

## External services

After you connect, this bundle talks to [FeatValue](https://www.featvalue.com/) so the client portal can run on your site.

FeatValue may then receive the site address and name, whether the site is connected, which pages show the portal, and, if you turn the options on, the name, email and user ID of signed-in Contao users, or mail recipients, subject and message.

Privacy: https://www.iubenda.com/privacy-policy/16160214

Support: hello@featvalue.io
