# Telegram Bridge

## Installation

Create in the extension directory (usually forum/ext) the subdirectories eb/telegram and copy the code
into that directory.

## Create a Telegram Bot

You need to define a telegram bot, through which all telegram communication with the forum is handled.
Don't worry, this is really very easy: https://core.telegram.org/bots#how-do-i-create-a-bot
Just think about a good name and description. Nothing else is needed for this extension.
Well, you need the bot token in the following step.

## Enable and customise the extension
Go to "ACP" > "Customise" > "Extensions" and enable the "Telegram Bridge" extension.

Go to "ACP" > "Extensions" and select "Telegram Bridge Settings".
Enter all settings there and press the link, which is provided for registering the webHook.

## Testing
Send any text to the bot. It should react with the information, that you need to enter
your telegram-ID into your profile.
Also don't forget to select in your profile notification settings the notifications you want to
receive via telegram. And inform your users, to do the same, if they want to use the telegram bridge.

## Side effects
In order to be able to follow the full conversation on telegram, a notification is send on every new post, even if the forum was not visited in the meantime.
As a side effect, the same is true for email-notifications, although the default email text states something different.
Therefore you may also consider to change the corresponding email-texts under <forum-root>/language/<your_language>/email.
If you want to include the full post text also into the emails, you may use the placeholder {TELEGRAM_MESSAGE}.
Corresponding email templates are bookmark.txt, forum_notify.txt, topic_notify.txt and newtopic_notify.txt.

## License

[GNU General Public License v2](license.txt)
