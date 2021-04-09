<?php

namespace App\Controller\Content;

use App\Controller\SiteCacheController;
use App\Service\SettingsService;
use App\Template\Layout;
use Doctrine\DBAL\DBALException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class EmailTemplateController extends SiteCacheController
{
    public function __construct(EntityManagerInterface $em, ContainerBagInterface $params, RequestStack $requestStack, SessionInterface $session, SettingsService $objSettingsService)
    {
        parent::__construct($em, $params, $requestStack, $session, $objSettingsService);
    }

    /**
     * @Route("/sendmailTemplate", name="frontoffice_email_template_sendemail", methods={"POST"})
     */
    public function sendmailTemplate(Request $request): Response
    {
        $contentEmailTemplateReferenceKey = $request->request->get('content_email_template');
        $contentEmailTemplateTable = $request->request->get('content_email_template_table');

        $defaultLanguage = $request->getLocale();

        // reCAPTCHA Google ---------------------------- //
            $objLayout = new Layout( $this->params, $this->requestStack, $this->session, $this->objSettingsService);

            if (!$objLayout->isDev($request) && !$objLayout->isStaging($request)) {
                $recaptcha = $request->request->get('g-recaptcha-response');

                $secretKey = '';
                $arSecretKey = $this->objSettingsService->getSettingsVars('GOOGLE_CAPTCHA_SECRET_KEY_V2');
                if (is_array($arSecretKey)) {
                    $arLanguages = $this->objSettingsService->getEnvVars('SUPPORTED_LOCALES');
                    $localePosition = array_search($defaultLanguage, $arLanguages);

                    if (isSet($arSecretKey[$localePosition])) {
                        $secretKey = $arSecretKey[$localePosition];
                    }
                }

                $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) . '&response=' . urlencode($recaptcha);
                $response = file_get_contents($url);
                $arRet = json_decode($response, true);

                if ($arRet && array_key_exists('success', $arRet) && $arRet['success'] == false) {
                    if (array_key_exists('error-codes', $arRet) && is_array($arRet['error-codes']) && count($arRet['error-codes']) > 0) {
                        return $this->json([
                            'return' => 'error',
                            'msg' => $arRet['error-codes'][0]
                        ]);
                    }
                }
            }
        // --------------------------------------------- //

        $this->request->getMethod();    // e.g. GET, POST, PUT, DELETE or HEAD
        $this->request->getLanguages(); // an array of languages the client accepts

        $message = null;
        if ($this->request->getMethod() === 'POST') {
            $arSendmailTo = $this->objSettingsService->getSettingsVars('SENDMAIL_TO');
            if ($arSendmailTo && is_array($arSendmailTo)) {
                $mailTo = $arSendmailTo[0];
            } else {
                $mailTo = '';
            }

            if (!$mailTo) {
                return $this->json(['return' => 'error', 'msg' => 'Variable SENDMAIL_TO not filled in .settings']);
            }

            $arPost = $_POST;
            foreach ($arPost AS $field => $value) {
                if ($field !== 'content_email_template' && $field !== 'content_email_template_save') {
                    if (is_array($value) && $field === 'additional_fields') {
                        foreach($value AS $key => $val) {
                            $arFields[strToUpper($key)] = $val;
                        }
                    }

                    $arFields[strToUpper($field)] = $value;
                }
            }

            if (array_key_exists('ADDITIONAL_FIELDS', $arFields)) {
                unset($arFields['ADDITIONAL_FIELDS']);
            }

            $arData = [
                'language' => $defaultLanguage,
                'fields' => $arFields,
                'mailTo' => $mailTo,
                'referenceKey' => $contentEmailTemplateReferenceKey,
                'table' => $contentEmailTemplateTable,
            ];

            $objData = [];
            if ($data = $this->setAPIData($this->apiUrl . '/api/sendEmailTemplate', $arData)) {
                $objData = json_decode($data);
            }
            return $this->json($objData);
        }
    }
}
