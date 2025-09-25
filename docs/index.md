# Laravel Helpdesk Documentation

## Overview

Laravel Helpdesk is a comprehensive package that integrates full-featured helpdesk functionality into any Laravel application. It provides ticket management, SLA tracking, automation rules, knowledge base, and much more.

## Table of Contents

### Getting Started
- [Installation](./installation.md)
- [Configuration](./configuration.md)
- [Quick Start Guide](./quick-start.md)

### Core Features
- [Ticket Management](./features/ticket-management.md)
- [Categories & Tags](./features/categories-tags.md)
- [Comments & Attachments](./features/comments-attachments.md)
- [Subscriptions & Notifications](./features/subscriptions-notifications.md)

### Advanced Features
- [Voice Notes & Transcription](./features/voice-notes.md)
- [AI-Powered Analysis](./features/ai-powered-analysis.md)
- [SLA Management](./features/sla-management.md)
- [Automation Rules](./features/automation-rules.md)
- [Time Tracking](./features/time-tracking.md)
- [Ticket Relations](./features/ticket-relations.md)
- [Bulk Actions](./features/bulk-actions.md)
- [Rating System](./features/rating-system.md)
- [Response Templates](./features/response-templates.md)
- [Knowledge Base](./features/knowledge-base.md)
- [Workflow Management](./features/workflow-management.md)

### API Reference
- [Service Classes](./api/services.md)
- [Models](./api/models.md)
- [Events](./api/events.md)
- [Enums](./api/enums.md)
- [Exceptions](./api/exceptions.md)

### Developer Guide
- [Architecture Overview](./developer/architecture.md)
- [Extending the Package](./developer/extending.md)
- [Custom Automation Actions](./developer/custom-automation.md)
- [Event System](./developer/events.md)
- [Testing](./developer/testing.md)

### Examples
- [Common Use Cases](./examples/use-cases.md)
- [Integration Examples](./examples/integrations.md)
- [Automation Templates](./examples/automation-templates.md)

## Package Features Summary

### Current Features

#### Core Functionality
- **Ticket Management**: Complete ticket lifecycle management with status transitions, priorities, and types
- **Categorization**: Hierarchical categories using nested sets for efficient organization
- **Tagging System**: Flexible tagging for classification and searching
- **Comments**: Threaded discussions with internal notes support
- **Attachments**: File attachment management for tickets and comments
- **Subscriptions**: User subscription system for ticket notifications

#### Advanced Features
- **Voice Notes & Transcription**: Create and respond to tickets with voice recordings, automatic transcription and emotional tone analysis
- **AI-Powered Analysis**: Intelligent ticket analysis with sentiment detection and response suggestions
- **SLA Management**: Service Level Agreement tracking with breach warnings
- **Automation Engine**: Rule-based automation with conditions and actions
- **Time Tracking**: Built-in time tracking with billing support
- **Ticket Relations**: Parent-child and related ticket relationships
- **Bulk Operations**: Mass actions on multiple tickets
- **Rating System**: Customer satisfaction ratings with feedback
- **Response Templates**: Predefined response templates for common scenarios
- **Knowledge Base**: FAQ and knowledge article management with AI suggestions
- **Workflow Service**: Custom workflow management for ticket processing

#### Technical Features
- **Event-Driven Architecture**: Comprehensive event system for all operations
- **Notification System**: Multi-channel notification support (email, logging, custom)
- **Configuration Management**: Extensive configuration options
- **Factory Support**: Database factories for testing
- **Service-Oriented Design**: Clean separation of concerns with service classes


## Quick Links

- [GitHub Repository](https://github.com/masterix21/laravel-helpdesk)
- [Issue Tracker](https://github.com/masterix21/laravel-helpdesk/issues)
- [Changelog](../CHANGELOG.md)
- [License](../LICENSE.md)