<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryAreaTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaStruct;
use Shopware\Core\System\Language\LanguageStruct;

class CountryAreaTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $countryAreaId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var CountryAreaStruct|null
     */
    protected $countryArea;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCountryAreaId(): string
    {
        return $this->countryAreaId;
    }

    public function setCountryAreaId(string $countryAreaId): void
    {
        $this->countryAreaId = $countryAreaId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCountryArea(): ?CountryAreaStruct
    {
        return $this->countryArea;
    }

    public function setCountryArea(CountryAreaStruct $countryArea): void
    {
        $this->countryArea = $countryArea;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}
