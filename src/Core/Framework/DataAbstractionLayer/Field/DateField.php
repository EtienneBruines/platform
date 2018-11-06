<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer\ValueTransformer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer\ValueTransformerDate;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class DateField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName)
    {
        $this->storageName = $storageName;
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $key, $value);
        }

        yield $this->storageName => $this->getValueTransformer()->transform($value);
    }

    /**
     * @param array  $constraints
     * @param string $fieldName
     * @param mixed  $value
     */
    private function validate(array $constraints, string $fieldName, $value)
    {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $this->validator
                ->validate($value, $constraint);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $violationList->add(
                    new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        $fieldName,
                        $violation->getInvalidValue(),
                        $violation->getPlural(),
                        $violation->getCode(),
                        $violation->getConstraint(),
                        $violation->getCause()
                    )
                );
            }
        }

        if (\count($violationList)) {
            throw new InvalidFieldException($this->path . '/' . $fieldName, $violationList);
        }
    }

    /**
     * @return array
     */
    private function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->isDate()
            ->getConstraints();
    }

    /**
     * @return ValueTransformer
     */
    private function getValueTransformer(): ValueTransformer
    {
        return $this->valueTransformerRegistry
            ->get(ValueTransformerDate::class);
    }
}