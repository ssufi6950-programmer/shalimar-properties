# Local network access

Configured on 6 November 2026 for the current Wi-Fi connection.

- Server IPv4: `10.100.101.63`
- Network: `10.100.100.0/23`
- Apache HTTP port: `80`
- Customer portal: <http://10.100.101.63/shalimar-properties/public/customer/dashboard>
- Customer login: <http://10.100.101.63/shalimar-properties/public/customer/login>
- Public website: <http://10.100.101.63/shalimar-properties/html/index.php>

Open these addresses from a device on the same reachable Wi-Fi/LAN. Each device signs in using its own session. Existing localhost URLs continue to work.

Apache already listens on `0.0.0.0:80` and `[::]:80` (`Listen 80`). The active Windows Public network profile already has an enabled TCP allow rule for `D:\xampp\apache\bin\httpd.exe`. No Apache restart, new firewall rule or network-profile change was needed. An unrelated Codex sandbox block is scoped to a different Windows account from the Apache process; it was not changed.

The portal previously returned localhost links even when opened through the LAN IP. `Config\App` now accepts an exact JSON hostname allowlist from the private deployment environment. Local settings retain the localhost base URL and allow `127.0.0.1` and `10.100.101.63`; CI4 generates links for the accepted request hostname. Unknown hosts do not become trusted redirect/asset hosts.

SMS testing remains enabled with its existing recipient. Its override may now be used from the explicitly configured private subnet `10.100.100.0/23`, while outside peers, malformed/overbroad subnet declarations and public production base URLs remain blocked. This avoids breaking OTP delivery from a second LAN device and never falls back to actual customer numbers in test mode. On-screen OTP preview remains restricted to loopback requests under its existing guard.

Application files changed: `app/Config/App.php`, `app/Config/Sms.php`, `app/Services/JazzCustomerSms.php`, plus this machine's private `.env` hostname/subnet settings. No ERP database, API folder or credential was changed.

Verified HTTP 200 login pages at localhost, 127.0.0.1 and the LAN IP, with matching hostnames for assets, login links and OTP POST endpoints. SMS recipient/subnet tests used a capturing gateway and sent no real SMS. Existing customer config and local OTP restriction checks passed. LAN-generated assets were fetched successfully; an unknown Host header retained the configured canonical hostname.

A second physical device was not available to this tool for an end-to-end Wi-Fi check. If router client isolation separates devices, they must be placed on the same reachable LAN. If DHCP changes this computer's address/subnet, update the private hostname/subnet entries to match the new address.
