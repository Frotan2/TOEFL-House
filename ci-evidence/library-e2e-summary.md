## Library browser E2E failure

```
Error: notice "Book issued." never appeared; mutations=[{"username":"e2e-library-librarian","method":"POST","path":"/api/v1/resources/books"}] state={"url":"/library","notice":"Book copy registered.","alert":null,"dialogs":[{"kind":"prompt","message":"Due date (YYYY-MM-DD)","default":"2026-09-12","returned":"null"},{"kind":"confirm","message":"Issue Browser E2E volume 1789239756272 to Authority Fixture e2e-library-approver-1?","returned":false}],"busy":false,"text":"Skip to main content T TOEFL House ACADEMIC OPERATIONS PLATFORM Authorized workspace Navigate ⌘/Ctrl K My work Administration Sign out NAVIGATE WORK Home Reception Desk Students & Admissions Academic Operations Placement People & Faculty PEOPLE HR CRM & Follow-up OPERATIONS Finance & Funding Payroll Library & Resources Communication Reports & Dashboards Documents & Evidence GOVERNANCE Organization Identity Access Governance Privacy & Consent Audit & History CONTROL Command Center My work queue A"}
Error: notice "Book issued." never appeared; mutations=[{"username":"e2e-library-librarian","method":"POST","path":"/api/v1/resources/books"}] state={"url":"/library","notice":"Book copy registered.","alert":null,"dialogs":[{"kind":"prompt","message":"Due date (YYYY-MM-DD)","default":"2026-09-12","returned":"null"},{"kind":"confirm","message":"Issue Browser E2E volume 1789239756272 to Authority Fixture e2e-library-approver-1?","returned":false}],"busy":false,"text":"Skip to main content T TOEFL House ACADEMIC OPERATIONS PLATFORM Authorized workspace Navigate ⌘/Ctrl K My work Administration Sign out NAVIGATE WORK Home Reception Desk Students & Admissions Academic Operations Placement People & Faculty PEOPLE HR CRM & Follow-up OPERATIONS Finance & Funding Payroll Library & Resources Communication Reports & Dashboards Documents & Evidence GOVERNANCE Organization Identity Access Governance Privacy & Consent Audit & History CONTROL Command Center My work queue A"}
    at waitForNotice (file:///home/runner/work/TOEFL-House/TOEFL-House/scripts/runtime/library-browser-e2e.mjs:279:13)
    at async file:///home/runner/work/TOEFL-House/TOEFL-House/scripts/runtime/library-browser-e2e.mjs:426:3
```

Records completed: 4

| Record | Result | Detail |
|---|---|---|
| Health endpoint is reachable | PASS |  |
| Library browser session is authenticated | PASS |  |
| Library workspace renders authorized facts | PASS |  |
| Book copy is registered through the canonical API | PASS |  |

Console errors: none
