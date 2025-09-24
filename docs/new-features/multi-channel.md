# Multi-Channel Support

## Overview

Multi-channel support enables ticket creation and management across various communication channels including email, SMS, social media, webhooks, and API integrations. This creates a unified support experience regardless of how customers choose to contact support.

## Supported Channels

### 1. Email Integration
- **Inbound Processing**: Convert emails to tickets automatically
- **Email Piping**: Direct email forwarding to ticket system
- **IMAP/POP3 Support**: Connect to any email provider
- **Smart Parsing**: Extract ticket metadata from email content
- **Attachment Handling**: Automatic attachment processing

### 2. SMS/WhatsApp
- **Two-way Messaging**: Send and receive SMS messages
- **WhatsApp Business API**: Full WhatsApp integration
- **Auto-responses**: Configurable automatic replies
- **Media Support**: Handle images and documents via MMS

### 3. Social Media
- **Facebook Messenger**: Direct message handling
- **Twitter/X Integration**: Monitor mentions and DMs
- **Instagram Direct**: Support through Instagram messages
- **LinkedIn Messages**: B2B support channel

### 4. Live Chat
- **Website Widget**: Embeddable chat widget
- **Real-time Messaging**: WebSocket-based communication
- **Agent Routing**: Intelligent agent assignment
- **Chat History**: Persistent conversation history

### 5. API & Webhooks
- **REST API**: Full-featured ticket API
- **GraphQL Support**: Flexible data queries
- **Webhook Receivers**: Accept tickets from any source
- **Custom Integrations**: Extensible channel system

## Technical Architecture

### Channel Manager
```php
namespace LucaLongo\LaravelHelpdesk\Channels;

interface ChannelInterface
{
    public function receive($data): Ticket;
    public function send($ticket, $message): bool;
    public function validate($data): bool;
    public function transform($data): array;
}

class ChannelManager
{
    protected $channels = [];
    
    public function register($name, ChannelInterface $channel)
    {
        $this->channels[$name] = $channel;
    }
    
    public function process($channel, $data)
    {
        return $this->channels[$channel]->receive($data);
    }
}
```

### Email Channel Implementation
```php
class EmailChannel implements ChannelInterface
{
    public function receive($data): Ticket
    {
        $parser = new EmailParser($data);
        
        return Ticket::create([
            'channel' => 'email',
            'subject' => $parser->getSubject(),
            'description' => $parser->getBody(),
            'sender_email' => $parser->getFrom(),
            'priority' => $this->detectPriority($parser),
            'attachments' => $parser->getAttachments(),
            'meta' => [
                'message_id' => $parser->getMessageId(),
                'in_reply_to' => $parser->getInReplyTo(),
                'headers' => $parser->getHeaders(),
            ],
        ]);
    }
}
```

### SMS Channel Implementation
```php
class SmsChannel implements ChannelInterface
{
    protected $provider; // Twilio, Vonage, etc.
    
    public function receive($data): Ticket
    {
        return Ticket::create([
            'channel' => 'sms',
            'subject' => 'SMS from ' . $data['from'],
            'description' => $data['body'],
            'sender_phone' => $data['from'],
            'priority' => $this->detectUrgency($data['body']),
            'meta' => [
                'provider' => $data['provider'],
                'message_sid' => $data['sid'],
            ],
        ]);
    }
    
    public function send($ticket, $message): bool
    {
        return $this->provider->send([
            'to' => $ticket->sender_phone,
            'body' => $message,
        ]);
    }
}
```

## Configuration

```php
'channels' => [
    'email' => [
        'enabled' => true,
        'driver' => 'imap',
        'host' => env('HELPDESK_EMAIL_HOST'),
        'port' => env('HELPDESK_EMAIL_PORT', 993),
        'encryption' => 'ssl',
        'username' => env('HELPDESK_EMAIL_USERNAME'),
        'password' => env('HELPDESK_EMAIL_PASSWORD'),
        'folder' => 'INBOX',
        'auto_reply' => true,
        'create_users' => true,
    ],
    
    'sms' => [
        'enabled' => true,
        'provider' => 'twilio',
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_PHONE_NUMBER'),
        'webhook_url' => '/webhooks/sms',
    ],
    
    'social' => [
        'facebook' => [
            'enabled' => true,
            'app_id' => env('FACEBOOK_APP_ID'),
            'app_secret' => env('FACEBOOK_APP_SECRET'),
            'webhook_token' => env('FACEBOOK_WEBHOOK_TOKEN'),
        ],
        'twitter' => [
            'enabled' => true,
            'consumer_key' => env('TWITTER_CONSUMER_KEY'),
            'consumer_secret' => env('TWITTER_CONSUMER_SECRET'),
            'access_token' => env('TWITTER_ACCESS_TOKEN'),
            'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
        ],
    ],
    
    'chat' => [
        'enabled' => true,
        'provider' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'encrypted' => true,
    ],
]
```

## Channel Routing Rules

```php
'routing' => [
    'rules' => [
        [
            'channel' => 'email',
            'condition' => 'subject_contains',
            'value' => 'urgent',
            'action' => 'set_priority',
            'priority' => 'high',
        ],
        [
            'channel' => 'sms',
            'condition' => 'always',
            'action' => 'assign_team',
            'team' => 'mobile_support',
        ],
        [
            'channel' => 'social',
            'condition' => 'platform',
            'value' => 'twitter',
            'action' => 'add_tag',
            'tag' => 'social_media',
        ],
    ],
]
```

## Unified Inbox

```php
class UnifiedInbox
{
    public function getMessages($filters = [])
    {
        return collect()
            ->merge($this->getEmailMessages($filters))
            ->merge($this->getSmsMessages($filters))
            ->merge($this->getSocialMessages($filters))
            ->merge($this->getChatMessages($filters))
            ->sortByDesc('created_at');
    }
    
    public function reply($ticket, $message, $channel = null)
    {
        $channel = $channel ?? $ticket->channel;
        
        return $this->channelManager
            ->get($channel)
            ->send($ticket, $message);
    }
}
```

## Channel Analytics

```php
class ChannelAnalytics
{
    public function getChannelDistribution($period = '30d')
    {
        return Ticket::query()
            ->select('channel', DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->sub($period))
            ->groupBy('channel')
            ->get();
    }
    
    public function getResponseTimeByChannel()
    {
        return Ticket::query()
            ->select(
                'channel',
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_response_time')
            )
            ->whereNotNull('first_response_at')
            ->groupBy('channel')
            ->get();
    }
}
```

## Implementation Timeline

### Phase 1: Email & API (2 weeks)
- Email channel setup
- API endpoints
- Basic routing rules

### Phase 2: SMS & Chat (3 weeks)
- SMS provider integration
- Live chat widget
- Real-time messaging

### Phase 3: Social Media (4 weeks)
- Facebook integration
- Twitter integration
- Instagram/LinkedIn

### Phase 4: Unified Experience (2 weeks)
- Unified inbox
- Cross-channel analytics
- Advanced routing

## Benefits

- **Customer Convenience**: Support through preferred channels
- **Increased Reach**: Meet customers where they are
- **Faster Response**: Real-time channels reduce resolution time
- **Better Tracking**: Comprehensive multi-channel analytics
- **Cost Efficiency**: Automated routing and responses