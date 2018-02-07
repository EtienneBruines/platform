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

namespace Shopware\Api\Entity\Write;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityForeignKeyResolver;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\MappingEntityDefinition;
use Shopware\Api\Entity\Write\FieldAware\DefaultExtender;
use Shopware\Api\Entity\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Entity\Write\Command\DeleteCommand;
use Shopware\Api\Entity\Write\Command\InsertCommand;
use Shopware\Api\Entity\Write\Command\UpdateCommand;
use Shopware\Api\Entity\Write\Command\WriteCommandInterface;
use Shopware\Api\Entity\Write\Command\WriteCommandQueue;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolation;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolationException;

class EntityWriter implements EntityWriterInterface
{
    /**
     * @var DefaultExtender
     */
    private $defaultExtender;

    /**
     * @var EntityForeignKeyResolver
     */
    private $foreignKeyResolver;

    /**
     * @var WriteCommandExtractor
     */
    private $writeResource;

    /**
     * @var EntityWriteGatewayInterface
     */
    private $gateway;

    public function __construct(
        WriteCommandExtractor $writeResource,
        DefaultExtender $defaultExtender,
        EntityForeignKeyResolver $foreignKeyResolver,
        EntityWriteGatewayInterface $gateway
    ) {
        $this->defaultExtender = $defaultExtender;
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->writeResource = $writeResource;
        $this->gateway = $gateway;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $commandQueue = $this->buildCommandQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($commandQueue);

        $this->gateway->execute($commandQueue);

        return $writeIdentifiers;
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $commandQueue = $this->buildCommandQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($commandQueue);

        $commandQueue->ensureIs($definition, InsertCommand::class);
        $this->gateway->execute($commandQueue);

        return $writeIdentifiers;
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $commandQueue = $this->buildCommandQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($commandQueue);

        $commandQueue->ensureIs($definition, UpdateCommand::class);

        $this->gateway->execute($commandQueue);

        return $writeIdentifiers;
    }

    /**
     * @param EntityDefinition|string $definition
     * @param array                   $ids
     * @param WriteContext            $writeContext
     *
     * @throws RestrictDeleteViolationException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return array
     */
    public function delete(string $definition, array $ids, WriteContext $writeContext): array
    {
        $this->validateWriteInput($ids);

        $commandQueue = new WriteCommandQueue();
        $commandQueue->setOrder($definition, ...$definition::getWriteOrder());

        $fields = $definition::getPrimaryKeys();

        $resolved = [];
        foreach ($ids as $raw) {
            $mapped = [];

            /** @var StorageAware|IdField $field */
            foreach ($fields as $field) {
                if (!array_key_exists($field->getPropertyName(), $raw)) {
                    throw new \InvalidArgumentException(
                        sprintf('Missing primary key value %s for entity %s', $field->getPropertyName(), $definition::getEntityName())
                    );
                }

                $mapped[$field->getStorageName()] = $raw[$field->getPropertyName()];
            }

            $resolved[] = $mapped;
        }

        $instance = new $definition();
        if (!$instance instanceof MappingEntityDefinition) {
            $restrictions = $this->foreignKeyResolver->getAffectedDeleteRestrictions($definition, $resolved);

            if (!empty($restrictions)) {
                $restrictions = array_map(function ($restriction) {
                    return new RestrictDeleteViolation($restriction['pk'], $restriction['restrictions']);
                }, $restrictions);

                throw new RestrictDeleteViolationException($restrictions);
            }
        }

        $cascades = [];
        if (!$instance instanceof MappingEntityDefinition) {
            $cascadeDeletes = $this->foreignKeyResolver->getAffectedDeletes($definition, $resolved);

            $cascadeDeletes = array_column($cascadeDeletes, 'restrictions');
            foreach ($cascadeDeletes as $cascadeDelete) {
                $cascades = array_merge_recursive($cascades, $cascadeDelete);
            }
        }

        foreach ($resolved as $mapped) {
            $mapped = array_map(function($id) {
                return Uuid::fromString($id)->getBytes();
            }, $mapped);

            $commandQueue->add($definition, new DeleteCommand($definition, $mapped));
        }

        $identifiers = $this->getWriteIdentifiers($commandQueue);
        $this->gateway->execute($commandQueue);

        return array_merge_recursive($identifiers, $cascades);
    }

    private function getWriteIdentifiers(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        /*
         * @var string
         * @var UpdateCommand[]|InsertCommand[] $queries
         */
        foreach ($queue->getCommands() as $resource => $commands) {
            if (count($commands) === 0) {
                continue;
            }

            $identifiers[$resource] = [];
            /** @var WriteCommandInterface[] $commands */
            foreach ($commands as $command) {
                $primaryKey = $this->getCommandPrimaryKey($command);
                $payload = $this->getCommandPayload($command);

                $identifiers[$resource][] = [
                    'primaryKey' => $primaryKey,
                    'payload' => $payload
                ];
            }
        }

        return $identifiers;
    }

    private function buildCommandQueue(string $definition, array $rawData, WriteContext $writeContext): WriteCommandQueue
    {
        $commandQueue = new WriteCommandQueue();

        $extender = new FieldExtenderCollection();
        $extender->addExtender($this->defaultExtender);

        /* @var EntityDefinition $definition */
        $commandQueue->setOrder($definition, ...$definition::getWriteOrder());

        $commandQueue = new WriteCommandQueue();
        $exceptionStack = new FieldExceptionStack();

        foreach ($rawData as $row) {
            $writeContext->resetPaths();
            $this->writeResource->extract($row, $definition, $exceptionStack, $commandQueue, $writeContext, $extender);
        }
        $exceptionStack->tryToThrow();

        return $commandQueue;
    }

    private function validateWriteInput(array $data): void
    {
        $valid = array_keys($data) === range(0, count($data) - 1);

        if (!$valid) {
            throw new \InvalidArgumentException('Expected input to be array.');
        }
    }

    private function getCommandPrimaryKey(WriteCommandInterface $command)
    {
        $fields = $command->getDefinition()::getPrimaryKeys();

        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware|Field $field */
            $field = $fields->first();

            return Uuid::fromBytes($primaryKey[$field->getStorageName()])->toString();
        }

        /** @var StorageAware|Field $field */
        foreach ($fields as $field) {
            $data[$field->getPropertyName()] = Uuid::fromBytes($primaryKey[$field->getStorageName()])->toString();
        }

        return $data;
    }

    private function getCommandPayload(WriteCommandInterface $command): array
    {
        /** @var InsertCommand|UpdateCommand $command */
        $payload = $command instanceof DeleteCommand ? [] : $command->getPayload();

        $fields = $command->getDefinition()::getFields();

        $convertedPayload = [];
        foreach ($payload as $key => $value) {
            $field = $fields->getByStorageName($key);

            if ($field instanceof IdField || $field instanceof FkField) {
                $value = Uuid::fromBytes($value)->toString();
            }
            $convertedPayload[$field->getPropertyName()] = $value;
        }

        $primaryKeys = $fields->filterByFlag(PrimaryKey::class);

        /** @var Field|StorageAware $primaryKey */
        foreach ($primaryKeys as $primaryKey) {
            if (array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw new \RuntimeException(
                    sprintf('Primary key field %s::%s not found in payload or command primary key', $command->getDefinition(), $primaryKey->getStorageName())
                );
            }

            $key = $command->getPrimaryKey()[$primaryKey->getStorageName()];

            $convertedPayload[$primaryKey->getPropertyName()] = Uuid::fromBytes($key)->toString();
        }

        return $convertedPayload;
    }
}