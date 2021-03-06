<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\PriceField;
use Shopware\Core\Framework\ORM\Field\PriceRulesJsonField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\ReverseInherited;
use Shopware\Core\System\Tax\TaxDefinition;

class ProductServiceDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_service';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ProductDefinition::class),
            (new FkField('configuration_group_option_id', 'optionId', ConfigurationGroupOptionDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ConfigurationGroupOptionDefinition::class),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(TaxDefinition::class),
            new PriceField('price', 'price'),
            new PriceRulesJsonField('prices', 'prices'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false))->setFlags(new ReverseInherited('services')),
            new ManyToOneAssociationField('option', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, true),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, true),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ProductServiceCollection::class;
    }

    public static function getStructClass(): string
    {
        return ProductServiceStruct::class;
    }
}
