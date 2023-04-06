# Telegram Bridge

## Update

**Important**: If you update from a previous version, de-activate the extension, before installing the new version.

Note: Unfortunately, as a side effect, the notification settings of the users get lost, when an extension with a version prior to 1.0.4-dev is deactivated. You can avoid that, by installing the intermediate version v1.0.3-prepare1.0.4 and following exactly the process described there.
## Installation

### From release
Download the latest [release zip](https://github.com/D-MBLD/phpbb-telegram-bridge/releases) and expand it into the extension folder (\<forums-root\>/ext) of your forum.

### From newest gitHub version (master branch).
Create in the extension directory (usually \<forums-root\>/ext) the subdirectories eb/telegram. Download the code as zip (See "code"-button in GitHub) and extract everything below "phpbb-telegram-bridge-master" directly into the eb/telegram subdirectory.

## Create a Telegram Bot

You need to define a telegram bot, through which all telegram communication with the forum is handled.
Don't worry, this is really very easy: https://core.telegram.org/bots#how-do-i-create-a-bot
Just think about a good name and description. Nothing else is needed for this extension.
Well, you need the bot token in the following step.

## Define Bot Commands

If you like, you may define the commands start and update for the bot. Therefore navigate with BotFather to 
"edit commands" and enter something like  
start - Show all forums  
update - Update

## Enable and customise the extension
Go to "ACP" > "Customise" > "Extensions" and enable the "Telegram Bridge" extension.

Go to "ACP" > "Extensions" and select "Telegram Bridge Settings".
Enter all settings there and press the link, which is provided for registering the webHook.
When pressing the link, the telegram server should return a message containing the information "Webhook was set".

## Testing
Send an arbitrary text to the bot. It should react with the information, that you need to enter
your telegram-ID into your profile.
Also don't forget to select in your profile notification settings the events for which you want to
receive notifications via telegram. And inform your users, to do the same, if they want to use the telegram bridge.

## Side effects
In order to be able to follow the full conversation on telegram, a notification is send on every new post, even if the forum was not visited in the meantime.
As a side effect, the same is true for email-notifications, although the default email text states something different.
Therefore you may also consider to change the corresponding email-texts under <forum-root>/language/<your_language>/email.
If you want to include the full post text also into the emails, you may use the placeholder {TELEGRAM_MESSAGE}.
Corresponding email templates are bookmark.txt, forum_notify.txt, topic_notify.txt and newtopic_notify.txt.

## Disclaimer
I did my best to ensure, that no user gets unauthorized access to read forums or to post into forums via telegram.
But I do not give any guarantee. If you install this extension, it is on your own responsibility.
 
## License

[GNU General Public License v2](license.txt)
