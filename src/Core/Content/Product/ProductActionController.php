<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ProductActionController extends Controller
{
    /**
     * @var VariantGenerator
     */
    private $generator;

    public function __construct(VariantGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @Route("/api/v{version}/product/{productId}/actions/generate-variants", name="api.product.actions.generate-variants")
     * @Method({"POST"})
     *
     * @param Request $request
     * @param string  $productId
     * @param Context $context
     *
     * @throws Exception\NoConfiguratorFoundException
     * @throws Exception\ProductNotFoundException
     *
     * @return JsonResponse
     */
    public function generateVariants(Request $request, string $productId, Context $context): JsonResponse
    {
        $offset = $request->query->get('offset', null);
        $limit = $request->query->get('limit', null);

        $events = $this->generator->generate($productId, $context, $offset, $limit);

        $event = $events->getEventByDefinition(ProductDefinition::class);

        return new JsonResponse(
            ['data' => $event->getIds()]
        );
    }
}
