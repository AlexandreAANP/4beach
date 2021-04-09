<?php

namespace App\Controller;

use App\Controller\Forms\FormsController;
use App\Controller\SiteCacheController;
use App\Controller\Product\ProductHighlightedController as ProductHighlighted;
use App\Entity\SiteAccess;
use App\Service\SettingsService;
use Doctrine\DBAL\DBALException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class DefaultController extends SiteCacheController
{
    private $twig;

    public function __construct(EntityManagerInterface $em, ContainerBagInterface $params, RequestStack $requestStack, SessionInterface $session, SettingsService $objSettingsService, Environment $twig)
    {
        $this->twig = $twig;
        parent::__construct($em, $params, $requestStack, $session, $objSettingsService);
    }

    /**
     * @Route("/", name="frontoffice_index", methods={"GET"})
     */
    public function index(Request $request)
    {
        $this->setCacheFilename('home');
        $defaultLanguage = $request->getLocale();

        $colProductHighlighted = [];
        $colProductHighlightedAll = [];
        $colProductCategoryHighlighted = [];

        if ((count($this->arProductType) === 1 && trim($this->arProductType[0] !== '')) || count($this->arProductType) > 1) {
            $colAllHighlighted = [];
            
            $categoryId = '';
            if ($colHighlighted = ProductHighlighted::get($this, $this->arProductType, $categoryId, $defaultLanguage)) {
              // dd(ProductHighlighted::get($this, $this->arProductType, $categoryId, $defaultLanguage));
                $colProductCategoryHighlighted = array_key_exists('colProductCategoryHighlighted', $colHighlighted) ? $colHighlighted['colProductCategoryHighlighted'] : [];
                //dd($colProductCategoryHighlighted);
                $colAllHighlighted = array_key_exists('colProducts', $colHighlighted) ? $colHighlighted['colProducts'] : [];
                
            }

            $objForms = new FormsController();
            foreach ($colAllHighlighted AS $productHighlighted) {
                $productType = $productHighlighted['productTypeReferenceKey'];
                if (!array_key_exists($productType, $colProductHighlighted)) {
                    $colProductHighlighted[$productType] = [];

                }

                if (array_key_exists('productAdditionalFields', $productHighlighted)) {
                
                    $colProductAdditionalFields = $objForms->getFields($productHighlighted['productAdditionalFields'], [
                        'colProductCategory' => $productHighlighted['colProductCategory']
                    ]);
                   
                    $productHighlighted['productAdditionalFields'] = $colProductAdditionalFields;
                }

                $colProductHighlighted[$productType][] = $productHighlighted;

                $colProductHighlightedAll[] = $productHighlighted;
            }
        }

        if ($colProductHighlighted) {
            $colProductHighlighted['all'] = $colProductHighlightedAll;
        }
        
        $colHomeSlider = [];
        $url = $this->apiUrl . '/api/content?area=content-area-home-slider&fields=url,text&language=' . $request->getLocale();
        if ($data = $this->getAPIData($url)) {
            if ($objData = json_decode($data, JSON_UNESCAPED_UNICODE)) {
                if (array_key_exists('colContent', $objData)) {
                    $colHomeSlider = $objData['colContent'];
                }
            }
        }
       // dd($colProductHighlighted, $colProductCategoryHighlighted, $colHomeSlider);
        return $this->renderSite('index.html.twig', [
            'colProductHighlighted'         => $colProductHighlighted,
            'colProductCategoryHighlighted' => $colProductCategoryHighlighted,
            'colHomeSlider' => $colHomeSlider
        ]);
    }

    /**
     * @Route("/cookieConsent", name="frontoffice_cookie_consent", methods={"POST"})
     */
    public function cookieConsent()
    {
        $appVersionFrontOffice = $this->objSettingsService->getEnvVars('APP_VERSION_FRONTOFFICE');

        $date = $this->getDate('now');
        $content = 'Privacy Policy ID: ' . $appVersionFrontOffice . ' | Agreed in ' . $date;

        $objCookieConsentController = new CookieConsentController();
        $objCookieConsentController->setConsent($this->em, $this->domainName, $appVersionFrontOffice, $this->appVersionCookiePolicy);

        $cookie = new Cookie('cookie-consent-' . $this->appVersionCookiePolicy, $content, strtotime('now + 1 year'));

        $response = new Response();
        $response->headers->setCookie($cookie);

        $response->setContent(json_encode([
            'gtmHead' => $this->twig->render('_includes/gtm_head.html.twig'),
            'gtmBody' => $this->twig->render('_includes/gtm_body.html.twig')
        ]));

        $response->headers->set('Content-Type', 'application/json');
        $response->send();
        exit();
    }

    /**
     * @Route("/getAPI", name="frontoffice_get_api", methods={"POST"})
     */
    public function getAPI(Request $request)
    {
        $url = $request->request->get('url');
        $return = [];
        if ($data = $this->getAPIData($this->apiUrl . '/api/' . $url)) {
            $return = json_decode($data, JSON_UNESCAPED_UNICODE);
        }
        if (!array_key_exists('return', $return)) {
            $return['return'] = 'success';
        }
        return $this->json($return);
    }

    /**
     * @Route("/getSettingsTranslationFile", name="frontoffice_get_api_settings_translation_file", methods={"GET"})
     */
    public function getSettingsTranslationFile(Request $request)
    {
        $return = 'success';

        $domainName = $this->objSettingsService->getEnvVars('DOMAIN_NAME');
        if ($data = $this->getAPIData($this->apiUrl . '/api/getSettingsTranslationFile?domain=' . $domainName)) {
            if ($objData = json_decode($data, JSON_UNESCAPED_UNICODE)) {
                if (array_key_exists('colSettingsTranslationFile', $objData)) {
                    $arContent = $objData['colSettingsTranslationFile'];

                    $arContent = $this->organizeTranslationFileContent($arContent);

                    $this->translationFileContentSave($arContent);
                }
            }
        }
        return $this->json(['return' => $return]);
    }

    function translationFileContentSave($arContent) {
        $arContentSettings = $arContent[0];
        $arContentMessages = $arContent[1];

        $arContentFile = [];
        foreach ($arContentSettings AS $filename => $arContent) {
            $file = '.' . $filename;

            $text = '';
            foreach ($arContent AS $key => $arVal) {
                $text .= $key . '=';

                $line = 0;
                foreach ($arVal AS $val) {
                    if ($line > 0) {
                        $text .= '|';
                    }

                    $val = str_replace('"', '`', $val);

                    $text .= '"' . $val . '"';

                    $line++;
                }
                $text .= "\n";
            }

            $arContentFile[$file] = $text;
        }

        $arMessageFile = [];
        foreach ($arContentMessages AS $filename => $arContent) {
            foreach ($arContent AS $lang => $arVal) {
                $text = '';
                foreach ($arVal AS $key => $val) {
                    $text .= $key . ': ' . $val . "\n";
                }

                $arMessageFile[$filename . '.' . $lang . '.yaml'] = $text;
            }
        }

        foreach ($arContentFile AS $filename => $content) {
            $filepath = dirname($_SERVER['DOCUMENT_ROOT']) . '/' . $filename;
            $fo = fopen($filepath, 'w') or die('Unable to open file!');
            fwrite($fo, $content);
            fclose($fo);
        }

        foreach ($arMessageFile AS $filename => $content) {
            $filepath = dirname($_SERVER['DOCUMENT_ROOT']) . '/translations/' . $filename;
            $fo = fopen($filepath, 'w') or die('Unable to open file!');
            fwrite($fo, $content);
            fclose($fo);
        }

        // TODO: Passar isso pro .ENV
        $filepath = dirname($_SERVER['DOCUMENT_ROOT']) . '/var';
        if ($filepath === '/home/ibiz/subdomains/nome-do-dominio/www/var') {
            shell_exec('rm -rf ' . $filepath);
        }
    }

    function organizeTranslationFileContent($arContent) {
        $arRet = [];

        foreach ($arContent AS $content) {
            $filename = $content['fileName'];
            $referenceKey = $content['referenceKey'];
            $name = $content['name'];
            $translationCode = $content['translationCode'];

            $isMessage = $content['isMessage'];

            if (!array_key_exists($isMessage, $arRet)) {
                $arRet[$isMessage] = [];
            }

            if (!array_key_exists($filename, $arRet[$isMessage])) {
                $arRet[$isMessage][$filename] = [];
            }

            if ($isMessage) {
                if (!array_key_exists($translationCode, $arRet[$isMessage][$filename])) {
                    $arRet[$isMessage][$filename][$translationCode] = [];
                }

                if (!array_key_exists($referenceKey, $arRet[$isMessage][$filename][$translationCode])) {
                    $arRet[$isMessage][$filename][$translationCode][$referenceKey] = $name;
                }

            } else {
                if (!array_key_exists($referenceKey, $arRet[$isMessage][$filename])) {
                    $arRet[$isMessage][$filename][$referenceKey] = [];
                }

                if (!array_key_exists($translationCode, $arRet[$isMessage][$filename][$referenceKey])) {
                    $arRet[$isMessage][$filename][$referenceKey][$translationCode] = $name;
                }

            }
        }

        return $arRet;
    }

    /**
     * @Route("/getDebugTranslation", name="frontoffice_get_debug_translation", methods={"GET"})
     */
    public function getDebugTranslation(Request $request)
    {
        $fileId = $request->get('file', 3);
        $trans = $request->get('trans', '1,2');
        $dbid = $request->get('dbid', '1');

        $filename = $filepath = dirname($_SERVER['DOCUMENT_ROOT']) . '/translation.txt';
        if (!is_file($filename)) {
            dd('Run: php bin/console debug:translation pt > translation.txt');
        }
        $content = file_get_contents($filename);
        $arContent = explode("\n", $content);

        echo '<b>1) Informe um queryparameter (?file=' . $fileId . ') para o `settings_translation_file_content_id` do File `message`</b><BR>';
        echo '<b>2) Informe um queryparameter (&trans=' . $trans . ') com os IDs da tabela `translation`, ex: 1,2</b><BR>';
        echo '<b>3) Informe um queryparameter (&dbid=' . $dbid . ') com o ID inicial / do último registro da tabela `settings_translation_file_content.id`</b><BR>';
        echo '<HR>';

        $arTrans = explode(',', $trans);

        $arColumnLength = [];
        foreach ($arContent AS $i => $line) {
            $line = trim($line);

            if ($i > 2 && $i + 3 < count($arContent)) {
                $col01 = substr($line, 0, $arColumnLength[0]);
                $col02 = substr($line, $arColumnLength[0], $arColumnLength[1]);
                $col03 = substr($line, $arColumnLength[0] + $arColumnLength[1], $arColumnLength[2]);

                $col03 = trim($col03);
                $name = htmlspecialchars($col03);

                $sql = 'INSERT INTO `settings_translation_file_content` (id, reference_key, settings_translation_file_id, active) VALUES (' . $dbid . ', "' . $name . '", ' . $fileId . ', 1);';
                echo $sql . '<br>';
                foreach ($arTrans AS $transId) {
                    $sql = 'INSERT INTO `settings_translation_file_content_translation` (settings_translation_file_content_id, translation_id, name) VALUES (' . $dbid . ', ' . $transId . ', "");';
                    echo $sql . '<br>';
                }
                echo '<br>';
                $dbid++;

            } else if ($i === 0) {
                $arLine = explode(' ' , $line);
                foreach ($arLine AS $col) {
                    $arColumnLength[] = strlen($col);
                }
            }
        }
        exit();
    }
}