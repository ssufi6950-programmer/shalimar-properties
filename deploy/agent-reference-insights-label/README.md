# Agent WhatsApp Property Reference and Insights Labels

Local deployment handoff. These changes are implemented locally; they have not been uploaded to live.

## Changes

- The Shalimar Agent approval WhatsApp template's second body variable now formats the property as `BBT-04 Tower (SP-64)`: plot/property number, display type, and the listing reference in parentheses.
- Insights project logo/name pills are static labels. They no longer link to project pages.

## Exact FTP paths

| Local package file | Live destination | Action |
| --- | --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/agent-reference-insights-label/app/Services/AgentAssignmentWhatsapp.php` | `/home/propertys_user11/www/app/Services/AgentAssignmentWhatsapp.php` | Back up, then replace |
| `D:/xampp/htdocs/shalimar-properties/deploy/agent-reference-insights-label/html/insights.php` | `/home/propertys_user11/www/html/insights.php` | Back up, then replace |

No SQL or `.env` changes are required. This package has not been deployed and no WhatsApp message was sent for testing.
