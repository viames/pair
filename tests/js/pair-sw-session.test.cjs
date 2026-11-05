const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { test } = require('node:test');

/** Load the complete worker with synthetic network responses and no persistent cache. */
function harness(response) {
	const handlers = {};
	const source = fs.existsSync('assets/PairSW.js') ? 'assets/PairSW.js' : 'public/js/PairSW.js';
	const context = vm.createContext({ URL, Response, self: { location: new URL('https://example.invalid/app/sw.js'), addEventListener(name, fn) { handlers[name] = fn; } } });
	vm.runInContext(fs.readFileSync(source, 'utf8'), context);
	context.testResponse = response;
	vm.runInContext('executeCacheStrategy = async () => testResponse;', context);
	return (mode = 'navigate') => {
		let result;
		handlers.fetch({request: {method: 'GET', mode, url: 'https://example.invalid/app/tools/'}, respondWith(promise) { result = promise; }});
		return result;
	};
}

/** Build the exact authentication contract independently of browser Fetch Metadata. */
function expired(loginUrl = '/app/login', extra = {}) {
	return new Response(JSON.stringify({code: 'AUTH_SESSION_EXPIRED', loginUrl}), {
		status: 401, headers: {'X-Pair-Auth-Expired': '1', 'Content-Type': 'application/json'}, ...extra
	});
}

test('expired document navigation redirects to the same-origin login', async () => {
	const response = expired();
	const result = await harness(response)();
	assert.equal(result.status, 302);
	assert.equal(result.headers.get('Location'), 'https://example.invalid/app/login');
	assert.equal(response.bodyUsed, false);
});

test('fetch and PairRouter requests retain the original readable 401 JSON', async () => {
	const response = expired();
	assert.equal(await harness(response)('same-origin'), response);
	assert.equal((await response.json()).code, 'AUTH_SESSION_EXPIRED');
});

for (const loginUrl of ['https://external.invalid/login', '//external.invalid/login', 'javascript:alert(1)', 'https://user:password@example.invalid/app/login', '/app/tools/', '']) {
	test(`unsafe or looping destination is not followed: ${loginUrl}`, async () => {
		const response = expired(loginUrl);
		assert.equal(await harness(response)(), response);
		assert.equal(response.bodyUsed, false);
	});
}

for (const response of [expired('/app/login', {status: 403}), expired('/app/login', {headers: {}}), new Response('{invalid', {status: 401, headers: {'X-Pair-Auth-Expired': '1'}}), new Response('{"code":"OTHER_ERROR","loginUrl":"/app/login"}', {status: 401, headers: {'X-Pair-Auth-Expired': '1'}})]) {
	test('unrelated or malformed authentication responses remain unchanged', async () => {
		assert.equal(await harness(response)(), response);
		assert.equal(response.bodyUsed, false);
	});
}
