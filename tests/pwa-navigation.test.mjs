import assert from 'node:assert/strict';
import test from 'node:test';
import {
    mobilePlatform,
    mobileDestinations,
    mobileTabActive,
    mobileUrl,
    mobileView,
} from '../resources/js/lib/mobile/navigation.ts';

test('mobile screens use existing page URLs and preserve business filters', () => {
    const current = '/finance?year=2025&account=12#totals';
    assert.equal(
        mobileUrl(current, 'menu'),
        '/finance?year=2025&account=12&mobile=menu#totals',
    );
    assert.equal(mobileUrl(mobileUrl(current, 'profile'), null), current);
    assert.equal(mobileView('/dashboard?mobile=menu'), 'menu');
    assert.equal(mobileView('/dashboard?mobile=unknown'), null);
    assert.equal(mobileView('/dashboard'), null);
});
test('bottom bar contains only destinations supplied by authorized navigation', () => {
    assert.deepEqual(mobileDestinations([]), []);
    const items = [
        { title: 'Inicio', href: '/dashboard' },
        {
            title: 'Operaciones',
            href: '/operations',
            children: [{ title: 'Agenda', href: '/operations/agenda' }],
        },
    ];
    assert.deepEqual(
        mobileDestinations(items).map((item) => item.key),
        ['home', 'agenda', 'operations'],
    );
    assert.equal(
        mobileDestinations(items).some((item) => item.key === 'commercial'),
        false,
    );
});
test('active tab matches details and keeps Agenda separate from Operations', () => {
    for (const path of [
        '/commercial',
        '/people/23',
        '/quotes/8',
        '/opportunities/4',
    ]) {
        assert.equal(mobileTabActive(path, '/commercial'), true);
    }

    assert.equal(
        mobileTabActive('/operations/agenda?view=day', '/operations'),
        false,
    );
    assert.equal(mobileTabActive('/operations/planning', '/operations'), true);
    assert.equal(mobileTabActive('/dashboard?year=2026', '/dashboard'), true);
    assert.equal(mobileTabActive('/dashboarding', '/dashboard'), false);
});

test('platform styling combines platform hints with input capabilities and has a safe fallback', () => {
    assert.equal(mobilePlatform('iPhone', 'iPhone', 5, true), 'ios');
    assert.equal(mobilePlatform('Safari', 'MacIntel', 5, true), 'ios');
    assert.equal(mobilePlatform('Android Chrome', 'Linux', 5, true), 'android');
    assert.equal(mobilePlatform('iPhone', 'MacIntel', 0, false), 'universal');
    assert.equal(mobilePlatform('Unknown', 'Unknown', 2, true), 'universal');
});
