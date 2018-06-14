<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct\ConfigurationGroupOptionTranslationDetailStruct;

class ConfigurationGroupOptionTranslationDetailCollection extends ConfigurationGroupOptionTranslationBasicCollection
{
    /**
     * @var ConfigurationGroupOptionTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getConfigurationGroupOptions(): ConfigurationGroupOptionBasicCollection
    {
        return new ConfigurationGroupOptionBasicCollection(
            $this->fmap(function (ConfigurationGroupOptionTranslationDetailStruct $configurationGroupOptionTranslation) {
                return $configurationGroupOptionTranslation->getConfigurationGroupOption();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ConfigurationGroupOptionTranslationDetailStruct $configurationGroupOptionTranslation) {
                return $configurationGroupOptionTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionTranslationDetailStruct::class;
    }
}