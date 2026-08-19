#!/usr/bin/env python3
from pathlib import Path

p = Path('tools/final-hardening-0-7-142.py')
s = p.read_text(encoding='utf-8')
old = r'''    "\n\t\t$doctor_admin = new Gloskin_Site_Core_Doctor_Migration_Admin( $plugin_file );\n\t\t$doctor_admin->register();\n\t\tself::$services[] = $doctor_admin;",
'''
new = r'''    "\n\t\t\t$doctor_admin = new Gloskin_Site_Core_Doctor_Migration_Admin( $plugin_file );\n\t\t\t$doctor_admin->register();\n\t\t\tself::$services[] = $doctor_admin;",
'''
if old not in s:
    raise SystemExit('doctor-admin driver source target not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
print('final-hardening-driver-fixes: OK')
