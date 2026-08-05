# 04_ARCHITECTURE_SNAPSHOT

## Merkez Model

Platformun merkezinde **Workspace** bulunur.

Workspace; kullanıcılar, roller, modüller, ayarlar ve işletme verileri için ortak çalışma alanıdır.

## Katmanlar

### Platform Core

- Workspace
- Authentication
- Authorization
- User Management
- Role & Permission Engine
- Module Engine
- Settings
- Media
- Notifications
- Translation
- Theme
- Backup
- API
- AI
- Automation
- Audit & Logging
- Security

### Shared Services

Sektör modüllerinin ortak kullanacağı servisler:

- CRM
- Finance
- HR
- Inventory
- Reports
- Files
- Documents
- Calendar
- Tasks
- Analytics
- Notifications
- AI

### Business Modules

- Restaurant
- Hotel
- Retail
- Clinic
- Beauty
- Manufacturing
- Service
- Education
- Diğer sektör modülleri

## Mimari Kurallar

- Modüller mümkün olduğunca gevşek bağlı çalışmalıdır.
- Ortak servisler sektör modüllerine özel hale getirilmemelidir.
- Bir sektör modülü, başka bir sektör modülüne doğrudan bağımlı olmamalıdır.
- Büyük mimari kararlar belgelenmeden uygulanmamalıdır.
