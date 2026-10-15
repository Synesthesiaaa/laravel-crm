# Integrations

These guides describe integration boundaries verified in source on 2026-10-15. No live external calls, SIP/media tests, webhook delivery, SMTP sends, workers or database queries were performed for this documentation. Configuration defaults are examples, not deployed topology.

- [VICIdial](vicidial.md): Agent/Non-Agent APIs, server mappings, session workflows, callbacks and push events
- [Asterisk AMI](asterisk-ami.md): TCP listener, webhook ingress, correlation and remaining originate path
- [Browser softphone](browser-softphone.md): agent UI responsibilities and media ownership
- [SIP and WebRTC](sip-webrtc.md): credential delivery, WSS and ICE boundaries
- [Laravel Reverb](laravel-reverb.md): application broadcasts, private auth and polling fallback
- [SMTP](smtp.md): email delivery integration

See the [developer API reference](../developer/api-reference.md), [operations](../operations/README.md), and [diagrams](../diagrams/README.md).

Existing [telephony phase guides](../telephony/AGENT_TELEPHONY_BUILD_PLAN.md), [Asterisk setup](../asterisk/ASTERISK_WEBRTC_SETUP.md), and [VICIdial direct integration guide](../asterisk/VICIDIAL_DIRECT_CRM_INTEGRATION_GUIDE.md) are preserved specialized/historical material. Reconcile their paths, process flags and architectural claims with current source before use; they do not establish deployed configuration or completed external validation.
