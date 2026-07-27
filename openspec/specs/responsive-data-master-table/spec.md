# Responsive Data Master Table

## Purpose

Keep Data Master records readable across desktop and mobile screens by preserving the complete desktop table and presenting mobile records as non-horizontally-scrollable stacked cards.

## Requirements

### Requirement: Data Master uses a complete desktop table
The Data Master page SHALL display all configured record columns and the Actions column in a semantic table at desktop viewport widths.

#### Scenario: Desktop record table
- **WHEN** an authenticated administrator opens Data Master at a desktop viewport with records available
- **THEN** the page displays the complete configured column set in the table, including the existing formatted values and Edit/Delete actions

### Requirement: Data Master uses stacked cards on mobile
The Data Master page SHALL display each record as a stacked card below the desktop breakpoint instead of requiring horizontal scrolling.

#### Scenario: Mobile record cards
- **WHEN** an authenticated administrator opens Data Master at a phone-sized viewport with records available
- **THEN** each record is shown as a card with every configured field rendered as a visible label/value pair and with Edit/Delete actions

#### Scenario: Long mobile content wraps
- **WHEN** a Data Master label or value is longer than the available card width
- **THEN** the content wraps within the viewport and the page does not create horizontal overflow for the Data Master surface

### Requirement: Data Master presentation preserves existing states
The responsive presentation SHALL preserve existing Data Master field formatting, empty-state messaging, form-type selection, pagination, and delete confirmation behavior.

#### Scenario: Form type and pagination remain available
- **WHEN** an administrator changes the form type or navigates to another Data Master page
- **THEN** the selected records, configured fields, and pagination continue to use the existing routes and query behavior in the appropriate desktop or mobile presentation

#### Scenario: Empty Data Master table
- **WHEN** the selected Data Master table has no records
- **THEN** the existing "No records found." empty state is displayed without a horizontally scrollable empty table

### Requirement: Other shared tables retain their current behavior
The responsive Data Master change SHALL NOT force other pages using the shared table component to switch to stacked cards or lose their existing wide-table behavior.

#### Scenario: Existing non-Data-Master table
- **WHEN** an administrator opens another page that uses the shared table component
- **THEN** that page retains its existing table presentation unless it explicitly opts into the new responsive card presentation
