<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use Shopware\Core\Defaults;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;

class VersionTest extends ApiTestCase
{
    /**
     * @var Client
     */
    private $unauthorizedClient;

    protected function setUp()
    {
        parent::setUp();

        $this->unauthorizedClient = $this->getClient();
        $this->unauthorizedClient->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);
    }

    public function unprotectedRoutesDataProvider()
    {
        return [
            ['GET', '/api/v1/info'],
            ['GET', '/api/v1/entity-schema.json'],
        ];
    }

    public function protectedRoutesDataProvider()
    {
        return [
            ['GET', '/api/v1/product'],
            ['GET', '/api/v1/tax'],
            ['POST', '/api/sync'],
        ];
    }

    /**
     * @dataProvider unprotectedRoutesDataProvider
     */
    public function testNonVersionRoutesAreUnprotected(string $method, string $url): void
    {
        $this->unauthorizedClient->request($method, $url);
        $this->assertNotEquals(
            Response::HTTP_UNAUTHORIZED,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should not be protected. (URL: ' . $url . ')'
        );
    }

    public function testAuthShouldNotBeProtected(): void
    {
        $this->unauthorizedClient->request('POST', '/api/oauth/token');
        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should be protected. (URL: /api/oauth/token)'
        );

        $response = json_decode($this->unauthorizedClient->getResponse()->getContent(), true);

        $this->assertEquals('The authorization grant type is not supported by the authorization server.', $response['errors'][0]['title']);
        $this->assertEquals('Check that all required parameters have been provided', $response['errors'][0]['detail']);
    }

    /**
     * @dataProvider protectedRoutesDataProvider
     */
    public function testVersionRoutesAreProtected(string $method, string $url): void
    {
        $this->unauthorizedClient->request($method, $url);
        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should be protected. (URL: ' . $url . ')'
        );
    }
}
