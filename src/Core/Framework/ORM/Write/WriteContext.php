<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\ORM\Write;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\System\Language\LanguageDefinition;

class WriteContext
{
    private const SPACER = '::';

    /**
     * @var array
     */
    public $paths = [];

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array[]
     *
     * @example
     * [
     *      product
     *          uuid-1 => null
     *          uuid-2 => uuid-1
     * ]
     */
    private $inheritance = [];

    private function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function addInheritance(string $definition, array $inheritance): void
    {
        if (!isset($this->inheritance[$definition])) {
            $this->inheritance[$definition] = [];
        }

        $this->inheritance[$definition] = array_replace_recursive(
            $this->inheritance[$definition],
            $inheritance
        );
    }

    public static function createFromContext(Context $context): self
    {
        $self = new self($context);
        $self->set(LanguageDefinition::class, 'id', $context->getLanguageId());

        return $self;
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @param string $value
     */
    public function set(string $className, string $propertyName, string $value): void
    {
        $this->paths[$this->buildPathName($className, $propertyName)] = $value;
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return mixed
     */
    public function get(string $className, string $propertyName)
    {
        $path = $this->buildPathName($className, $propertyName);

        if (!$this->has($className, $propertyName)) {
            throw new \InvalidArgumentException(sprintf('Unable to load %s: %s', $path, print_r($this->paths, true)));
        }

        return $this->paths[$path];
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return bool
     */
    public function has(string $className, string $propertyName): bool
    {
        $path = $this->buildPathName($className, $propertyName);

        return isset($this->paths[$path]);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param array                   $raw
     *
     * @return bool
     */
    public function isChild(string $definition, array $raw): bool
    {
        if (array_key_exists('parent', $raw)) {
            return true;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $definition::getFields()->get('parent');

        $fk = $definition::getFields()->getByStorageName(
            $parent->getStorageName()
        );

        if (isset($raw[$fk->getPropertyName()])) {
            return true;
        }

        if (!array_key_exists($definition, $this->inheritance)) {
            return false;
        }

        $inheritance = $this->inheritance[$definition];

        return isset($inheritance[$raw['id']]);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function resetPaths(): void
    {
        $this->paths = [];
        $this->set(LanguageDefinition::class, 'id', $this->context->getLanguageId());
    }

    public function createWithVersionId(string $versionId): self
    {
        return self::createFromContext($this->getContext()->createWithVersionId($versionId));
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return string
     */
    private function buildPathName(string $className, string $propertyName): string
    {
        return $className . self::SPACER . $propertyName;
    }
}
