## 1. VICIdial campaign-scope serialization

- [x] 1.1 Serialize all resolved mapped campaign codes with VICIdial's hyphen-delimited multi-campaign format.
- [x] 1.2 Ensure multi-campaign scopes never populate the single-campaign `campaign_id` parameter used by agent stats.

## 2. Regression coverage

- [x] 2.1 Add a feature fixture that returns historical rows only when the request uses the supported multi-campaign delimiter and verify CRM totals include every mapped campaign.
- [x] 2.2 Update existing mapped-campaign request assertions and run the focused historical reporting test suite.

## 3. Verification and specification

- [x] 3.1 Run Pint, focused PHPUnit tests, and OpenSpec validation.
- [x] 3.2 Synchronize the main telephony reporting specification, review the diff, and archive the completed change.
