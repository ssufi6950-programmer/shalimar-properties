<?php
namespace Config;

class Whatsapp extends \CodeIgniter\Config\BaseConfig
{
    public bool $enabled = false;
    public string $accessToken = '';
    public string $phoneNumberId = '';
    public string $apiVersion = 'v23.0';
    public string $templateName = 'temp';
    public string $languageCode = 'en_US';
    public string $secondBodyText = 'share this code with';
    public bool $logResponses = false;
    /** Meta webhook verification token; configure privately in .env. */
    public string $webhookVerifyToken = '';
    /** Meta App Secret used to validate X-Hub-Signature-256. */
    public string $appSecret = '';
}
