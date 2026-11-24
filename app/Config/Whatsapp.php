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
    /** Separate template configuration for approved Shalimar Agent assignments. */
    public string $agentAssignmentTemplateName = 'shalimar_p_dealer_message';
    public string $agentAssignmentLanguageCode = 'en';
    public string $agencyStatusTemplateName = 'agency_property_status_update';
    public string $agencyStatusLanguageCode = 'en_US';
    public string $secondBodyText = 'share this code with';
    public bool $logResponses = false;
    /** Meta webhook verification token; configure privately in .env. */
    public string $webhookVerifyToken = '';
    /** Meta App Secret used to validate X-Hub-Signature-256. */
    public string $appSecret = '';

    public function __construct()
    {
        parent::__construct();
        $this->agentAssignmentTemplateName = (string) env('whatsapp.agentAssignmentTemplateName', $this->agentAssignmentTemplateName);
        $this->agentAssignmentLanguageCode = (string) env('whatsapp.agentAssignmentLanguageCode', $this->agentAssignmentLanguageCode);
        $this->agencyStatusTemplateName = (string) env('whatsapp.agencyStatusTemplateName', $this->agencyStatusTemplateName);
        $this->agencyStatusLanguageCode = (string) env('whatsapp.agencyStatusLanguageCode', $this->agencyStatusLanguageCode);
    }
}
