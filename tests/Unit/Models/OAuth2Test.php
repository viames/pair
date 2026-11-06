<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Tests\Support\TestCase;

/** Exercises real OAuth models with a driver that cannot connect to any database. */
final class OAuth2Test extends TestCase {
	/** Check the revocation join, case-sensitive token equality, and both lifetime policies. */
	public function testValidation(): void {
		$result = $this->runPhpSnippet(<<<'PHP'
eval('namespace Pair\\Orm; final class Database {
 const OBJECT=2, COUNT=5;
 public static bool $enabled=true;
 public static array $queries=[];
 public static function load(string $sql,array $params,int $mode): mixed {
  self::$queries[]=$sql;
  if ($mode===self::COUNT) { return 1; }
  if (!str_contains($sql,"c.enabled=1") || !str_contains($sql,"INNER JOIN oauth2_clients")) { throw new \\RuntimeException("Revocation missing"); }
  return self::$enabled ? (object)["token"=>"exact-token","client_id"=>"fixture"] : null;
 }
}');
use Pair\Models\OAuth2Token;
use Pair\Orm\Database;
if (OAuth2Token::validateBearer('exact-token',3600)!=='fixture') { throw new RuntimeException('Valid token rejected'); }
if (!str_contains(Database::$queries[0],'t.created_at>DATE_SUB(NOW(), INTERVAL 3600 SECOND)')) { throw new RuntimeException('Fixed expiry'); }
if (OAuth2Token::validateBearer('EXACT-TOKEN',3600)!==null) { throw new RuntimeException('Case-insensitive token accepted'); }
if (!OAuth2Token::isValid('exact-token')) { throw new RuntimeException('Valid token rejected'); }
if (!str_contains(end(Database::$queries),'t.updated_at>')) { throw new RuntimeException('Refresh clock changed'); }
Database::$enabled=false;
if (OAuth2Token::isValid('exact-token')) { throw new RuntimeException('Revoked token accepted'); }
if (!OAuth2Token::isLegacyValid('exact-token')) { throw new RuntimeException('Frozen legacy contract changed'); }
$_SERVER['HTTP_AUTHORIZATION']='Basic '.base64_encode(' client : secret ');
if (OAuth2Token::basicCredentials()!==['id'=>'client','secret'=>'secret']) { throw new RuntimeException('Legacy Basic changed'); }
$_SERVER['HTTP_AUTHORIZATION']='Bearer token,old';
if (OAuth2Token::bearerToken()!=='token,old') { throw new RuntimeException('Legacy Bearer changed'); }
print 'OK';
PHP);
		$this->assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
		$this->assertSame('OK', $result['stdout']);
	}

	/** A fresh issuance performs one INSERT with the DB clock and never renews or deletes another token. */
	public function testFreshIssuance(): void {
		$result = $this->runPhpSnippet(<<<'PHP'
eval('namespace Pair\\Orm; final class Database {
 public static array $writes=[];
 public static function run(string $sql,array $params): int {
  if ($sql!=="INSERT INTO oauth2_tokens (client_id, token, created_at, updated_at) VALUES (?, ?, NOW(), NOW())") { throw new \\RuntimeException("Unexpected mutation"); }
  self::$writes[]=[$sql,$params];return 1;
 }
}');
$client=(new ReflectionClass(Pair\Models\OAuth2Client::class))->newInstanceWithoutConstructor();
foreach (['id'=>'fixture','enabled'=>true] as $name=>$value) { (new ReflectionProperty($client,$name))->setValue($client,$value); }
(new ReflectionProperty(Pair\Orm\ActiveRecord::class,'loadedFromDb'))->setValue($client,true);
$a=$client->createAccessToken();$b=$client->createAccessToken();
if ($a===$b || strlen($a)!==512 || count(Pair\Orm\Database::$writes)!==2) { throw new RuntimeException('Fresh issuance'); }
$response=$client->accessTokenResponse('orders:read');
$headers=(new ReflectionProperty($response,'headers'))->getValue($response);
if ($headers['Cache-Control']!=='no-store' || $headers['Pragma']!=='no-cache') { throw new RuntimeException('Cache prevention'); }
ob_start();$response->send();$payload=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
if ($payload['scope']!=='orders:read' || $payload['expires_in']!==3600 || $payload['token_type']!=='Bearer') { throw new RuntimeException('Token payload'); }
try { $client->accessTokenResponse('bad  scope'); throw new LogicException('Invalid scope accepted'); }
catch (InvalidArgumentException) {}
(new ReflectionProperty($client,'enabled'))->setValue($client,false);
try { $client->createAccessToken(); throw new LogicException('Disabled client issued token'); }
catch (RuntimeException $e) { if ($e instanceof LogicException) { throw $e; } }
if (count(Pair\Orm\Database::$writes)!==3) { throw new RuntimeException('Disabled mutation'); }
print 'OK';
PHP);
		$this->assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
		$this->assertSame('OK', $result['stdout']);
	}
}
