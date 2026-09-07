"""Insert the missing level/availability/offering steps into legacy tests.

A class may only be defined against an OPEN OFFERING for its branch, level and
period. Many legacy tests build program -> version -> published period and then
call defineClass directly, which the domain correctly rejects.

This inserts the missing steps immediately after the transitionPeriod(...)
call, reusing the officer/version/period variables already in scope, and
returns the offering so defineClass can bind to it.
"""
import re
import sys
import pathlib

TRANSITION = re.compile(
    r'^(?P<indent>[ \t]*)(?P<lead>(?:\$\w+\s*=\s*)?)app\(MaintainAcademicStructure::class\)'
    r'->transitionPeriod\((?P<args>[^;]*?)\);[ \t]*$',
    re.M,
)

total = 0
for path in sys.argv[1:]:
    p = pathlib.Path(path)
    src = p.read_text()

    if 'openOffering' in src:
        continue  # already builds an offering

    m = TRANSITION.search(src)
    if m is None:
        continue

    args = [a.strip() for a in m.group('args').split(',')]
    if len(args) < 2:
        continue
    officer = args[0]

    # Recover the variables holding the version and period results.
    vm = re.search(r'\$(\w+)\s*=\s*app\(MaintainAcademicStructure::class\)->publishVersion\(', src)
    pm = re.search(r'\$(\w+)\s*=\s*app\(MaintainAcademicStructure::class\)->definePeriod\(', src)
    if not vm or not pm:
        continue
    version, period = '$' + vm.group(1), '$' + pm.group(1)

    indent = m.group('indent')
    key = 'canon-' + re.sub(r'[^a-z0-9]+', '-', p.stem.lower())[:24]

    block = (
        f"\n{indent}// A class requires an OPEN OFFERING for its branch, level and period;\n"
        f"{indent}// the domain refuses to infer one.\n"
        f"{indent}$fixtureLevel = app(MaintainAcademicStructure::class)->defineLevel("
        f"{officer}, {version}['version_id'], 'lvl-{key}', 1, 'Level', 'A1', '{key}-lvl');\n"
        f"{indent}app(MaintainAcademicStructure::class)->declareBranchAvailability("
        f"{officer}, $this->bootstrapBranchId(), $fixtureLevel['level_id'], {period}['period_id'], '{key}-avail');\n"
        f"{indent}$fixtureOffering = app(MaintainAcademicStructure::class)->openOffering("
        f"{officer}, $this->bootstrapBranchId(), $fixtureLevel['level_id'], {period}['period_id'], 200, '{key}-offering');"
    )

    src = src[:m.end()] + block + src[m.end():]
    p.write_text(src)
    print('  patched %s' % path)
    total += 1

print('files patched: %d' % total)
