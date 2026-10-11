<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\Request;
use Waypoint\Http\UploadedFile;

final class RequestTest extends TestCase
{
    private array $serverBackup;
    private array $getBackup;
    private array $postBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
    }

    public function testCaptureReadsMethodAndPathIgnoringQueryString(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/customers/1/orders?status=open';

        $req = Request::capture();

        $this->assertSame('POST', $req->method);
        $this->assertSame('/customers/1/orders', $req->path);
    }

    public function testCaptureDefaultsMethodToGetAndPathToSlash(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        $req = Request::capture();

        $this->assertSame('GET', $req->method);
        $this->assertSame('/', $req->path);
    }

    public function testCaptureNormalizesHttpPrefixedHeaders(): void
    {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'abc';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';

        $req = Request::capture();

        $this->assertSame('abc', $req->headers['X-Custom-Header']);
        $this->assertSame('Bearer token', $req->headers['Authorization']);
    }

    public function testCaptureReadsGetIntoQuery(): void
    {
        $_GET = ['search' => 'widgets'];

        $req = Request::capture();

        $this->assertSame('widgets', $req->query('search'));
        $this->assertNull($req->query('missing'));
        $this->assertSame('fallback', $req->query('missing', 'fallback'));
    }

    public function testCapturePrefersJsonBodyOverPostWhenBothArePresent(): void
    {
        $_POST = ['name' => 'from-post'];
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';

        $req = Request::capture(rawBody: json_encode(['name' => 'from-json']));

        $this->assertSame(['name' => 'from-json'], $req->body());
        $this->assertSame('application/json; charset=utf-8', $req->headers['Content-Type']);
    }

    public function testCaptureParsesJsonOnlyForAJsonContentType(): void
    {
        // A cross-site <form enctype="text/plain"> can send exactly this body without a CORS preflight
        $_SERVER['CONTENT_TYPE'] = 'text/plain';
        $this->assertSame([], Request::capture(rawBody: '{"name":"forged"}')->body());

        unset($_SERVER['CONTENT_TYPE']);
        $this->assertSame([], Request::capture(rawBody: '{"name":"no-type"}')->body());

        $_SERVER['CONTENT_TYPE'] = 'application/vnd.api+json';
        $this->assertSame(['name' => 'suffix'], Request::capture(rawBody: '{"name":"suffix"}')->body());

        $_SERVER['CONTENT_TYPE'] = 'application/jsonp';
        $this->assertSame([], Request::capture(rawBody: '{"name":"not-json"}')->body());
    }

    public function testCaptureFallsBackToPostWhenNoJsonBodyPresent(): void
    {
        $_POST = ['name' => 'Widget'];

        $req = Request::capture();

        $this->assertSame(['name' => 'Widget'], $req->body());
        $this->assertSame('Widget', $req->body('name'));
        $this->assertSame('n/a', $req->body('missing', 'n/a'));
    }

    public function testInputIsAnAliasForBody(): void
    {
        $_POST = ['email' => 'a@b.com'];

        $req = Request::capture();

        $this->assertSame($req->body('email'), $req->input('email'));
    }

    public function testCaptureGeneratesAUuidV4IdWhenNoRequestIdHeaderIsSent(): void
    {
        $req = Request::capture();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $req->id
        );
    }

    public function testCaptureGeneratesADifferentIdOnEachCall(): void
    {
        $first = Request::capture();
        $second = Request::capture();

        $this->assertNotSame($first->id, $second->id);
    }

    public function testCaptureUsesTheIncomingXRequestIdHeaderVerbatim(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'upstream-supplied-id-not-a-uuid';

        $req = Request::capture();

        $this->assertSame('upstream-supplied-id-not-a-uuid', $req->id);
    }

    public function testCaptureFallsBackToTheXCorrelationIdHeaderWhenNoRequestIdIsSent(): void
    {
        $_SERVER['HTTP_X_CORRELATION_ID'] = 'correlation-abc-123';

        $req = Request::capture();

        $this->assertSame('correlation-abc-123', $req->id);
    }

    public function testCapturePrefersXRequestIdOverXCorrelationIdWhenBothAreSent(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'request-id-wins';
        $_SERVER['HTTP_X_CORRELATION_ID'] = 'correlation-id-loses';

        $req = Request::capture();

        $this->assertSame('request-id-wins', $req->id);
    }

    // -- fromParts(): the same request without superglobals --

    public function testFromPartsBuildsTheRequestWithoutTouchingTheSuperglobals(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/elsewhere', 'HTTP_X_GLOBAL' => 'nein'];
        $_GET = ['global' => '1'];

        $req = Request::fromParts('post', '/orders/7?page=2&tags[]=a', ['content-type' => 'application/json', 'X_REQUEST_ID' => 'abc-123'], '{"qty":3}', ['sid' => 's1'], https: true);

        $this->assertSame('POST', $req->method);
        $this->assertSame('/orders/7', $req->path);
        $this->assertSame(['page' => '2', 'tags' => ['a']], $req->query);
        $this->assertSame(['Content-Type' => 'application/json', 'X-Request-Id' => 'abc-123'], $req->headers);
        $this->assertSame('abc-123', $req->id);
        $this->assertSame(['qty' => 3], $req->body);
        $this->assertSame(['sid' => 's1'], $req->cookies);
        $this->assertTrue($req->https);
    }

    public function testFromPartsDecodesAUrlencodedFormAndPrefersAlreadyParsedFields(): void
    {
        $form = Request::fromParts('POST', '/', ['Content-Type' => 'application/x-www-form-urlencoded'], 'name=Ada&roles[]=admin');
        $this->assertSame(['name' => 'Ada', 'roles' => ['admin']], $form->body);

        $multipart = Request::fromParts('POST', '/', ['Content-Type' => 'multipart/form-data; boundary=x'], '--x...', form: ['name' => 'Ada']);
        $this->assertSame(['name' => 'Ada'], $multipart->body);
    }

    public function testFromPartsReadsAJsonLookingTextBodyAsNothing(): void
    {
        // A cross-site <form> can send this without a preflight: it must not count as JSON
        $req = Request::fromParts('POST', '/', ['Content-Type' => 'text/plain'], '{"admin":true}');

        $this->assertSame([], $req->body);
    }

    public function testCaptureCarriesCookiesHttpsAndTheBareAuthorizationVariable(): void
    {
        $cookieBackup = $_COOKIE;
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['AUTHORIZATION'] = 'Bearer t';
        $_COOKIE = ['sid' => 's1', 'list' => ['nested']];
        try {
            $req = Request::capture();
        } finally {
            $_COOKIE = $cookieBackup;
        }

        $this->assertFalse($req->https);
        $this->assertSame('Bearer t', $req->headers['Authorization']);
        // Only string cookies: PHP turns a[]=x into an array, which no cookie reader here expects
        $this->assertSame(['sid' => 's1'], $req->cookies);
    }

    public function testCaptureTurnsFilesAroundAndLeavesEmptyInputsOut(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wp');
        file_put_contents($tmp, 'pdf bytes');
        $backup = $_FILES;
        $_FILES = [
            'doc' => ['name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 9],
            'empty' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
            'photos' => [
                'name' => ['x.jpg', 'big.jpg', ''],
                'type' => ['image/jpeg', 'image/jpeg', ''],
                'tmp_name' => [$tmp, '', ''],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_NO_FILE],
                'size' => [9, 0, 0],
            ],
        ];
        try {
            $req = Request::capture();
        } finally {
            $_FILES = $backup;
        }

        $this->assertSame(['doc', 'photos'], array_keys($req->files));
        $this->assertSame('a.pdf', $req->file('doc')?->name);
        $this->assertSame('pdf bytes', $req->file('doc')->contents());
        $this->assertSame(9, $req->file('doc')->size);
        $this->assertNull($req->file('empty'));
        $this->assertNull($req->file('photos'));
        $this->assertCount(2, $req->files['photos']);
        $this->assertTrue($req->file('photos.0')?->isValid());
        $this->assertFalse($req->file('photos.1')?->isValid());
        unlink($tmp);
    }

    public function testAnUploadMovesOnceAndAFailedOneNotAtAll(): void
    {
        $file = UploadedFile::fromString('a.txt', 'text/plain', 'hello');
        $target = sys_get_temp_dir() . '/wp-upload-' . bin2hex(random_bytes(4));
        $file->moveTo($target);
        $this->assertSame('hello', file_get_contents($target));
        unlink($target);

        try {
            $file->moveTo($target);
            $this->fail('moved twice');
        } catch (\RuntimeException) {
        }
        $failed = new UploadedFile('b.txt', 'text/plain', 0, fn () => '', fn (string $path) => null, UPLOAD_ERR_PARTIAL);
        $this->expectException(\RuntimeException::class);
        $failed->contents();
    }
}
