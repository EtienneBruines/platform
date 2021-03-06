<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Validation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class RestrictDeleteViolationException extends ShopwareHttpException
{
    /**
     * @var RestrictDeleteViolation[]
     */
    protected $restrictions;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    /**
     * @param string|EntityDefinition   $definition
     * @param RestrictDeleteViolation[] $restrictions
     * @param int                       $code
     * @param null|\Throwable           $previous
     */
    public function __construct(string $definition, array $restrictions, $code = 0, \Throwable $previous = null)
    {
        $restriction = $restrictions[0];
        $usages = [];

        /**
         * @var string|EntityDefinition
         * @var string[]                $ids
         */
        foreach ($restriction->getRestrictions() as $entityDefinition => $ids) {
            $usages[] = sprintf('%s (%d)', $entityDefinition::getEntityName(), \count($ids));
        }

        $message = sprintf(
            'The delete request for %s was denied due to a conflict. The entity is currently in use by: %s',
            $definition::getEntityName(),
            implode(', ', $usages)
        );

        parent::__construct($message, $code, $previous);

        $this->restrictions = $restrictions;
        $this->definition = $definition;
    }

    /**
     * @return RestrictDeleteViolation[]
     */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
