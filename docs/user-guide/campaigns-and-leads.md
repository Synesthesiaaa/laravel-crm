# Campaigns and leads

## Know which context you are using

A **CRM campaign** organizes forms, capture fields, reporting, and server routing. Its display name may differ from its code. A **VICIdial campaign** is a phone-system campaign; administrators can map more than one to a CRM campaign. A **lead** identifies the customer or prospect in the calling workflow. A phone number alone may not uniquely identify a person.

Check the campaign label before entering information. Some management and reporting pages have their own campaign selector. Selecting a reporting campaign is not the same as changing the agent's live phone campaign. There is no general campaign-membership security guarantee: the campaign resolver selects an active context from the requested code, session, or first configured campaign; it does not itself check a per-user membership list.

## Find and verify a lead

1. Open **Agent Screen → Call tools → Lead**, if enabled.
2. Enter the phone number in **Lead Search & Actions** and select **Search**.
3. Use **Load Info** to inspect lead information and confirm the intended lead before choosing **Switch Lead**.
4. Confirm the lead ID and phone number in **Lead Information**. Existing lead data may populate capture fields automatically.
5. Correct information through the appropriate enabled lead control and wait for success. Saving a capture creates a local record; configured writable post/both mappings may also queue a separate VICIdial lead update. Local success does not prove that remote update completed.

The screen watches for active-lead updates and hydrates data from telephony sources. Availability depends on your server and session. If the wrong lead appears, stop before saving, note the lead ID and time, and ask a supervisor to investigate. Do not assume all visible values were entered manually or that a connection problem means the lead does not exist.

## Adding and moving between leads

Lead add, update, field update, and switch endpoints exist behind the **Lead tools** feature. Use only the operations exposed by your configured interface and allowed by your organization's process. Changing the lead context during an active call can associate records with the wrong customer. Finish pending capture and disposition work first.

Imported CRM form rows, Agent Screen captures, and VICIdial leads are different entities. The [Lead Hopper import limitation](../admin/campaigns.md#lead-hopper-imports) matters when a supervisor says that a CSV was uploaded but no dialable leads appeared.

Related: [forms and records](forms-and-records.md), [calls and dispositions](calls-and-dispositions.md), [administration campaigns](../admin/campaigns.md).

Source: [campaign resolver](../../app/Services/CampaignService.php), [lead controller](../../app/Http/Controllers/Api/LeadController.php), [Agent Screen](../../resources/views/agent/index.blade.php), [campaign mappings](../../app/Http/Controllers/Admin/CampaignsController.php).
