import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8');
const css = read('resources/css/app.css');
const sidebar = read('resources/js/components/ui/sidebar.tsx');
const nav = read('resources/js/components/nav-main.tsx');
test('mobile modal overrides the generic relative sidebar positioning', () => {
    const rule = css.match(/\[data-sidebar='sidebar'\]\[data-mobile='true'\] \{\n {4}position: fixed;[^}]+\}/)?.[0];
    assert.ok(rule, 'portalled sidebar must stay fixed above its overlay');
    assert.match(rule, /height: 100dvh/);
    assert.match(rule, /safe-area-inset-top/);
    assert.match(rule, /safe-area-inset-bottom/);
    assert.match(sidebar, /min\(360px, 88vw\)/);
    assert.doesNotMatch(sidebar, /\[&>button\]:hidden/);
});
test('modal delegates focus and dismissal to existing Radix Sheet', () => {
    assert.match(sidebar, /<Sheet open=\{openMobile\} onOpenChange=\{setOpenMobile\}/);
    assert.match(sidebar, /useState\(false\)/);
    assert.match(sidebar, /router.on\("navigate", \(\) => setOpenMobile\(false\)\)/);
    assert.match(sidebar, /mobileTriggerRef.current\?\.focus\(\)/);
    assert.match(css, /overscroll-behavior: contain/);
});
test('mobile and desktop consume the same authorized navigation', () => {
    assert.match(read('resources/js/components/app-sidebar.tsx'), /page.props.auth.navigation.map\(toNavItem\)/);
    assert.match(nav, /activeNavigation\(page.props.auth.navigation, page.url\)/);
    assert.match(nav, /onClick=\{\(\) => setOpenMobile\(false\)\}/);
    assert.match(nav, /<CollapsibleTrigger asChild>/);
    assert.match(nav, /<SidebarMenuAction/);
});
