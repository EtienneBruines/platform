<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;

class MediaAlbumDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_album';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),
            new FkField('parent_id', 'parentId', self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('create_thumbnails', 'createThumbnails'),
            new LongTextField('thumbnail_size', 'thumbnailSize'),
            new StringField('icon', 'icon'),
            new BoolField('thumbnail_high_dpi', 'thumbnailHighDpi'),
            new IntField('thumbnail_quality', 'thumbnailQuality'),
            new IntField('thumbnail_high_dpi_quality', 'thumbnailHighDpiQuality'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'media_album_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', MediaAlbumTranslationDefinition::class, 'media_album_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return MediaAlbumCollection::class;
    }

    public static function getStructClass(): string
    {
        return MediaAlbumStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return MediaAlbumTranslationDefinition::class;
    }
}
