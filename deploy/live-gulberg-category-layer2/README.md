# Gulberg City Category Layer 2

Admin Filter Builder now fetches exact `pro_inventory.category` values for Gulberg City from SGC inventory scoped to the project's current mapped Head and Sub Locations. Public project filters use those saved category values and match only exact, source/property/head/subhead-bound category snapshots in approved public listings. Shalimar Smart City remains on Sector; Zalmi X remains on mapped Sub Location.

## Upload files

Back up each existing destination, then FTP-merge/replace these files:

| Local source file | Live destination |
| --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/portal/app/Services/PropertyExplorer.php` | `/home/propertys_user11/www/app/Services/PropertyExplorer.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/portal/app/Services/ExplorerFilterBuilder.php` | `/home/propertys_user11/www/app/Services/ExplorerFilterBuilder.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/portal/app/Services/ExplorerErpClient.php` | `/home/propertys_user11/www/app/Services/ExplorerErpClient.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/portal/app/Services/PublicExplorerFilters.php` | `/home/propertys_user11/www/app/Services/PublicExplorerFilters.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/portal/app/Views/admin/projects/explorer.php` | `/home/propertys_user11/www/app/Views/admin/projects/explorer.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/sgc-api/src/FilterOptionsController.php` | `/home/sgc/www/html/smp-api/src/FilterOptionsController.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/live-gulberg-category-layer2/sgc-api/src/InventoryFieldDiscovery.php` | `/home/sgc/www/html/smp-api/src/InventoryFieldDiscovery.php` |

## After upload

1. In the Shalimar admin panel, open **Property Explorer → Filter Builder** and choose Gulberg City.
2. Use **Fetch Options from ERP**. Review the returned exact category values, set their display names/icons/visibility/order, and click **Save Selected Filters**. Fetching alone does not publish new filter options.
3. Check the Gulberg City page. The old Sub Location filters are no longer offered as Layer 2 choices; existing old Sub Location URLs remain resolvable where their saved records still exist.

No SQL or migration is required. No `.env` change is required. Existing project-to-ERP mappings are unchanged. Do not upload any `catalog.local.php` or credentials.

Listings are filtered only when their stored `_explorer_snapshot.fields["pro_inventory.category"]` has the exact verified value. Existing listings without that snapshot remain in **All**, but will not be guessed into a category filter. Fresh approved listing submissions capture this field when the SGC API returns it.
