<?php
declare(strict_types=1);

namespace Perspective\CheckoutField\Test\Integration\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Config\Dom;
use Magento\Framework\Module\Dir;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SystemXmlTest extends TestCase
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Dir
     */
    private $moduleDir;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $this->moduleDir = $objectManager->get(Dir::class);
    }

    /**
     * Test that system.xml file exists and is valid
     *
     * @return void
     */
    public function testSystemXmlFileExists(): void
    {
        $systemXmlPath = $this->getSystemXmlPath();
        $this->assertFileExists($systemXmlPath, 'system.xml file does not exist');
    }

    /**
     * Test that system.xml is valid XML
     *
     * @return void
     */
    public function testSystemXmlIsValid(): void
    {
        $systemXmlPath = $this->getSystemXmlPath();
        $xmlContent = file_get_contents($systemXmlPath);

        $this->assertNotEmpty($xmlContent, 'system.xml file is empty');

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertEmpty($errors, 'system.xml contains XML errors: ' . print_r($errors, true));
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml, 'Failed to parse system.xml');
    }

    /**
     * Test that required configuration fields are present in system.xml
     *
     * @return void
     */
    public function testRequiredFieldsExist(): void
    {
        $systemXmlPath = $this->getSystemXmlPath();
        $xml = simplexml_load_file($systemXmlPath);

        $xml->registerXPathNamespace('config', 'http://www.w3.org/2001/XMLSchema-instance');

        $sections = $xml->xpath("//section[@id='checkout_field']");
        $this->assertNotEmpty($sections, 'Section checkout_field does not exist');

        $groups = $xml->xpath("//section[@id='checkout_field']//group[@id='vat_validation']");
        $this->assertNotEmpty($groups, 'Group vat_validation does not exist');

        $requiredFields = ['enabled', 'required', 'allowed_countries', 'show_in_order_details'];
        foreach ($requiredFields as $fieldId) {
            $fields = $xml->xpath(
                "//section[@id='checkout_field']//group[@id='vat_validation']//field[@id='$fieldId']"
            );
            $this->assertNotEmpty($fields, "Field $fieldId does not exist in vat_validation group");
        }
    }

    /**
     * Test that default configuration values are loaded correctly
     *
     * @return void
     */
    public function testDefaultConfigurationValues(): void
    {
        $enabled = $this->scopeConfig->getValue(
            'checkout_field/vat_validation/enabled',
            ScopeInterface::SCOPE_STORE
        );
        $this->assertEquals('1', $enabled, 'Default enabled value is not correct');
        $required = $this->scopeConfig->getValue(
            'checkout_field/vat_validation/required',
            ScopeInterface::SCOPE_STORE
        );
        $this->assertEquals('0', $required, 'Default required value is not correct');
        $showInOrderDetails = $this->scopeConfig->getValue(
            'checkout_field/vat_validation/show_in_order_details',
            ScopeInterface::SCOPE_STORE
        );
        $this->assertEquals('1', $showInOrderDetails, 'Default show_in_order_details value is not correct');
    }

    /**
     * Test that allowed_countries has default EU countries
     *
     * @return void
     */
    public function testAllowedCountriesDefaultValue(): void
    {
        $allowedCountries = $this->scopeConfig->getValue(
            'checkout_field/vat_validation/allowed_countries',
            ScopeInterface::SCOPE_STORE
        );

        $this->assertNotEmpty($allowedCountries, 'Allowed countries should not be empty');
        $euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'PL', 'AT'];
        foreach ($euCountries as $countryCode) {
            $this->assertStringContainsString(
                $countryCode,
                $allowedCountries,
                "Country code $countryCode should be in allowed countries"
            );
        }
    }

    /**
     * Test that field dependencies are configured correctly
     *
     * @return void
     */
    public function testFieldDependencies(): void
    {
        $systemXmlPath = $this->getSystemXmlPath();
        $xml = simplexml_load_file($systemXmlPath);
        $requiredFieldDepends = $xml->xpath(
            "//section[@id='checkout_field']//group[@id='vat_validation']" .
            "//field[@id='required']//depends/field[@id='enabled']"
        );
        $this->assertNotEmpty($requiredFieldDepends, 'Field "required" should depend on "enabled" field');
        $allowedCountriesDepends = $xml->xpath(
            "//section[@id='checkout_field']//group[@id='vat_validation']" .
            "//field[@id='allowed_countries']//depends/field[@id='enabled']"
        );
        $this->assertNotEmpty(
            $allowedCountriesDepends,
            'Field "allowed_countries" should depend on "enabled" field'
        );
    }

    /**
     * Test that system.xml uses correct source models
     *
     * @return void
     */
    public function testSourceModels(): void
    {
        $systemXmlPath = $this->getSystemXmlPath();
        $xml = simplexml_load_file($systemXmlPath);
        $enabledSourceModel = $xml->xpath(
            "//section[@id='checkout_field']//group[@id='vat_validation']//field[@id='enabled']/source_model"
        );
        $this->assertNotEmpty($enabledSourceModel, 'Enabled field should have source_model');
        $this->assertEquals(
            'Magento\Config\Model\Config\Source\Yesno',
            (string)$enabledSourceModel[0],
            'Enabled field should use Yesno source model'
        );
        $countriesSourceModel = $xml->xpath(
            "//section[@id='checkout_field']//group[@id='vat_validation']//field[@id='allowed_countries']/source_model"
        );
        $this->assertNotEmpty($countriesSourceModel, 'Allowed countries field should have source_model');
        $this->assertEquals(
            'Magento\Directory\Model\Config\Source\Country',
            (string)$countriesSourceModel[0],
            'Allowed countries field should use Country source model'
        );
    }

    /**
     * Get path to system.xml file
     *
     * @return string
     */
    private function getSystemXmlPath(): string
    {
        $objectManager = Bootstrap::getObjectManager();
        $componentRegistrar = $objectManager->get(ComponentRegistrar::class);
        $modulePath = $componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Perspective_CheckoutField');

        return $modulePath . '/etc/adminhtml/system.xml';
    }
}
