<?php
namespace Apie\DoctrineEntityDatalayer\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;
use JsonException;

final class JsonArrayType extends JsonType
{
    public const NAME='text_json';
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 1024]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return parent::convertToDatabaseValue($value ?? [], $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR|JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw ConversionException::conversionFailed($value, $this->getName(), $e);
        }
    }

    public function getName()
    {
        return self::NAME;
    }
}
