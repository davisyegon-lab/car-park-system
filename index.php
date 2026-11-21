<?php
$publicDemo = getenv('PARKING_PUBLIC_DEMO') === '1';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'; base-uri \'none\'; frame-ancestors \'none\'; form-action \'self\'');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Strathmore Smart Parking</title>
    <link rel="stylesheet" href="style.css">
    <script src="app.js" defer></script>
</head>
<body>
<svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-grid" viewBox="0 0 24 24"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v5c0 5-3.1 8-8 10-4.9-2-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></symbol>
    <symbol id="i-sliders" viewBox="0 0 24 24"><path d="M4 7h7m4 0h5M4 17h3m4 0h9"/><circle cx="13" cy="7" r="2"/><circle cx="9" cy="17" r="2"/></symbol>
    <symbol id="i-car" viewBox="0 0 24 24"><path d="M5 16v3m14-3v3M4 12l2-6h12l2 6v4H4v-4Zm0 0h16M7 14h2m6 0h2"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
</svg>
<a class="skip" href="#main">Skip to content</a>
<header class="header">
    <div class="header-inner">
        <div class="brand"><span class="mark" aria-hidden="true">P</span><div><strong>Strathmore Smart Parking</strong><small>Parking &amp; access management</small></div></div>
        <button id="menu-toggle" type="button" aria-expanded="false" aria-controls="navigation" hidden><svg class="icon" aria-hidden="true"><use href="#i-menu"/></svg> Menu</button>
        <nav id="navigation" aria-label="Main navigation" data-open="false" hidden>
            <button class="nav-button" type="button" data-screen="dashboard" aria-controls="dashboard-screen"><svg class="icon" aria-hidden="true"><use href="#i-grid"/></svg> Dashboard</button>
            <button class="nav-button" type="button" data-screen="gate" aria-controls="gate-screen" hidden><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg> Gate operations</button>
            <button class="nav-button" type="button" data-screen="admin" aria-controls="admin-screen" hidden><svg class="icon" aria-hidden="true"><use href="#i-sliders"/></svg> Administration</button>
        </nav>
        <div class="header-actions">
            <button id="tour-button" class="quiet" type="button">Demo tour</button>
            <button id="open-login" type="button" data-open-screen="login" hidden><?= $publicDemo ? 'Admin / security sign-in' : 'Sign in' ?></button>
            <button id="logout" class="quiet" type="button" hidden>Sign out</button>
        </div>
    </div>
</header>
<main id="main" tabindex="-1">
    <p id="notice" class="notice" role="status" hidden></p>
    <section id="login-screen" class="screen login-layout" aria-labelledby="login-title" hidden>
        <div class="login-intro">
            <p class="eyebrow">Unofficial academic prototype</p>
            <h1 id="login-title" tabindex="-1">Campus parking</h1>
            <p>See which zones have room. At the gate, record arrivals, assign a zone and check vehicles out.</p>
            <div class="entry-illustration" aria-hidden="true">
                <div class="entry-bays"><span></span><span></span><span><svg class="icon"><use href="#i-car"/></svg></span><span></span></div>
                <div class="entry-lane"><span></span><svg class="icon"><use href="#i-car"/></svg></div>
            </div>
        </div>
        <form id="login-form" class="panel login-form">
            <h2>Sign in</h2>
            <p class="muted">Sign in to access your role’s parking tools.</p>
            <p id="login-error" class="notice error" role="alert"></p>
            <label>Email <input required type="email" name="email" autocomplete="username" maxlength="150" placeholder="admin@parking.test"></label>
            <label>Password <input required type="password" name="password" autocomplete="current-password" maxlength="72"></label>
            <button type="submit" class="primary">Sign in</button>
            <button id="back-to-map" type="button" class="quiet" data-open-screen="dashboard">Back to parking map</button>
            <details id="demo-credentials" data-public="<?= $publicDemo ? 'true' : 'false' ?>" hidden><summary>Demo credentials</summary>
                <p>Password for each role <strong>ParkingDemo26!</strong></p>
                <div class="demo-picks">
                    <button type="button" data-demo="security">Security</button><button type="button" data-demo="admin">Admin</button>
                </div>
            </details>
        </form>
    </section>
    <section id="dashboard-screen" class="screen" aria-labelledby="dashboard-title" hidden>
        <div class="page-heading dashboard-banner">
            <div><p class="eyebrow" id="identity"></p><h1 id="dashboard-title" tabindex="-1">Parking availability</h1>
                <p>General parking is first come, first served. Check the map for vacant, booked and unavailable bays.</p></div>
            <div class="dashboard-actions">
                <button type="button" id="open-gate" data-open-screen="gate" hidden><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg> Open gate desk</button>
                <button type="button" id="refresh-dashboard">Refresh availability</button>
                <small id="updated" class="pill" hidden></small>
            </div>
        </div>
        <p id="dashboard-status" class="notice" role="status"></p>
        <div id="dashboard-stats" class="stats"></div>
        <div class="section-heading zone-heading"><div><h2 id="campus-map-title" tabindex="-1">Campus parking map</h2><p class="muted">Select a zone to see its numbered bays and availability.</p></div>
            <ul class="map-legend" aria-label="Parking status key"><li class="available-key">Vacant</li><li class="pending-key">Selected</li><li class="occupied-key">Occupied</li><li class="unavailable-key">Unavailable</li><li class="booked-key">Booked</li></ul>
        </div>
        <section class="campus-board" aria-label="Illustrated campus plan">
            <div class="campus-key"><span>Campus plan</span><span>Illustrative layout</span></div>
            <div class="campus-plan">
                <div class="campus-building academic"><span>Central Building</span></div>
                <div id="campus-zone-a" class="campus-lot lot-a"></div>
                <div class="campus-building offices"><span>MSB Building</span></div>
                <div id="campus-zone-b" class="campus-lot lot-b"></div>
                <div class="campus-building reception"><span>Library</span></div>
                <div id="campus-zone-c" class="campus-lot lot-c"></div>
                <div class="campus-road" aria-hidden="true"></div>
                <div class="campus-entry"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg> Main gate</div>
            </div>
        </section>
        <p id="student-bay-help" class="bay-note muted" hidden><strong>Try the sensor demo.</strong> Select a vacant bay to simulate arrival. Select the same bay again to leave. <span id="simulation-scope">Public map changes apply to this tab only.</span></p>
        <p id="admin-bay-help" class="bay-note muted" hidden>Select a bay to book it for guests, mark it unavailable or return it to general parking.</p>
        <div id="dashboard-zones" class="zone-grid"></div>
        <p class="bay-note muted">Gate check-ins record the zone. The map places those vehicles in the first bays without a simulated arrival, booking or closure.</p>
    </section>
    <section id="gate-screen" class="screen" aria-labelledby="gate-title" hidden>
        <div class="page-heading desk-heading"><span class="desk-symbol"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg></span><div><p class="eyebrow">Security desk</p><h1 id="gate-title" tabindex="-1">Gate operations</h1><p>Check vehicles in, or search active sessions to check out or transfer a vehicle.</p></div></div>
        <div class="gate-grid">
            <form id="checkin-form" class="panel">
                <h2>Check in a vehicle</h2>
                <label>Driver type <select name="kind"><option value="institutional">Student or staff</option><option value="visitor">Visitor</option></select></label>
                <fieldset id="institution-fields">
                    <label>Search simulated AMS <input id="ams-search" type="search" maxlength="80" placeholder="Name or institutional ID"></label>
                    <label>Campus account <select id="ams-users" name="user_id" required></select></label>
                </fieldset>
                <fieldset id="visitor-fields" hidden disabled>
                    <label>Visitor name <input required name="name" maxlength="100"></label>
                    <label>Purpose of visit <input required name="purpose" maxlength="120" placeholder="Interview"></label>
                    <label>Department or destination <input required name="destination" maxlength="100"></label>
                    <label class="check"><input type="checkbox" name="id_inspected"> Physical ID inspected</label>
                    <label class="check"><input type="checkbox" name="id_retained"> Physical ID retained at gate</label>
                </fieldset>
                <label>Vehicle registration <input required name="vehicle_plate" maxlength="32" placeholder="DEMO701"></label>
                <label>Parking zone <select name="zone_id" id="checkin-zone" required></select></label>
                <div class="assignment-preview" aria-labelledby="assignment-title">
                    <div class="assignment-heading"><svg class="icon" aria-hidden="true"><use href="#i-car"/></svg><strong id="assignment-title">Parking assignment</strong></div>
                    <p id="assignment-state" role="status">Choose a zone to preview the assignment.</p>
                    <div id="assignment-map"></div>
                </div>
                <button type="submit" class="primary">Check in</button>
            </form>
            <section class="panel">
                <div class="section-heading"><h2>Active sessions</h2><button id="refresh-active" type="button">Refresh</button></div>
                <label>Search active sessions <input type="search" id="plate-search" maxlength="32" placeholder="Registration or #session number"></label>
                <p id="active-summary" role="status"></p>
                <div id="active-list" class="record-list"></div>
            </section>
        </div>
    </section>
    <section id="admin-screen" class="screen" aria-labelledby="admin-title" hidden>
        <div class="page-heading"><div><p class="eyebrow">Administrator</p><h1 id="admin-title" tabindex="-1">Administration</h1><p>Manage zones and review parking activity.</p></div></div>
        <nav class="tabs" aria-label="Administration sections">
            <button type="button" data-admin="zones" aria-controls="admin-zones" aria-pressed="true">Zones</button>
            <button type="button" data-admin="report" aria-controls="admin-report" aria-pressed="false">Reports</button>
            <button type="button" data-admin="audit" aria-controls="admin-audit" aria-pressed="false">Audit log</button>
            <button type="button" data-admin="accounts" aria-controls="admin-accounts" aria-pressed="false">Accounts</button>
        </nav>
        <section class="panel" id="admin-zones">
            <div class="section-heading"><h2>Parking zones</h2><button id="add-zone" class="primary" type="button">Add zone</button></div>
            <div id="zone-list" class="record-list"></div>
        </section>
        <section class="panel" id="admin-report" hidden>
            <h2>Parking activity</h2>
            <form id="report-filters" class="activity-filters" aria-describedby="report-window-help">
                <p id="report-window-help" class="muted">Dates use Nairobi time (EAT) and include both selected days. Leave both dates blank for today.</p>
                <div class="filter-fields">
                    <label>From <input type="date" name="from"></label>
                    <label>Through <input type="date" name="through"></label>
                </div>
                <fieldset>
                    <legend>Session history filters</legend>
                    <div class="filter-fields">
                        <label>Session number or campus registration <input type="search" name="q" maxlength="32" placeholder="#12 or DEMO001"></label>
                        <label>Last assigned zone <select name="zone_id"><option value="">All zones</option></select></label>
                        <label>Current session status <select name="status"><option value="">All statuses</option><option value="active">Active now</option><option value="closed">Checked out</option></select></label>
                    </div>
                </fieldset>
                <div class="filter-actions"><button type="submit" class="primary">Apply filters</button><button type="button" data-reset-filters>Reset</button></div>
            </form>
            <p id="report-status" class="activity-status" role="status"></p>
            <div id="report-results" hidden>
                <h3>Gate activity in the selected window</h3>
                <p id="report-window" class="muted"></p>
                <p class="muted">Entries count gate check-ins. Exits count recorded checkouts, including corrections. Transfers stay within the same session.</p>
                <div id="report-period-stats" class="stats"></div>
                <h3>Current occupancy and availability</h3>
                <p class="muted">Occupied spaces count vehicles currently signed in at the gate and student bay simulations. Booked and closed bays reduce availability.</p>
                <div id="report-stats" class="stats"></div>
                <div id="report-zones" class="zone-grid"></div>
                <h3>Session history</h3>
                <p class="muted">History includes sessions with an entry or exit during the window. Use the session number for visitor lookups.</p>
                <div id="history-list" class="record-list"></div>
            </div>
        </section>
        <section class="panel" id="admin-audit" hidden>
            <h2>Audit log</h2>
            <form id="audit-filters" class="activity-filters" aria-describedby="audit-window-help">
                <p id="audit-window-help" class="muted">Dates use Nairobi time (EAT) and include both selected days. Leave both dates blank to see recent events across all dates.</p>
                <div class="filter-fields">
                    <label>From <input type="date" name="from"></label>
                    <label>Through <input type="date" name="through"></label>
                    <label>Event <select name="event"><option value="">All events</option></select></label>
                    <label>Parking session number <input type="text" name="session_id" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="10" placeholder="12"></label>
                </div>
                <div class="filter-actions"><button type="submit" class="primary">Apply filters</button><button type="button" data-reset-filters>Reset</button></div>
            </form>
            <p id="audit-status" class="activity-status" role="status"></p>
            <div id="audit-results" hidden><div id="audit-list" class="record-list"></div></div>
        </section>
        <section class="panel" id="admin-accounts" hidden><h2>Demo accounts</h2><div id="account-list" class="record-list"></div></section>
    </section>
    <noscript><p>This application requires JavaScript to sign in and load parking records.</p></noscript>
</main>
<footer>Unofficial academic prototype · Use fictional records only</footer>
<dialog id="action-dialog" aria-labelledby="action-title">
    <form id="action-form">
        <h2 id="action-title"></h2>
        <p id="action-error" role="alert" class="notice error" hidden></p>
        <div id="action-fields"></div>
        <div class="dialog-actions"><button type="button" data-close="action-dialog">Cancel</button><button class="primary" id="action-submit" type="submit">Confirm</button></div>
    </form>
</dialog>
<dialog id="tour-dialog" aria-labelledby="tour-title">
    <h2 id="tour-title">Demo tour</h2>
    <progress id="tour-progress" max="10" value="1" aria-label="Demo progress"></progress>
    <p id="tour-step" class="eyebrow" aria-live="polite"></p>
    <h3 id="tour-heading"></h3>
    <p id="tour-copy"></p>
    <div class="dialog-actions">
        <button type="button" data-close="tour-dialog">Close</button>
        <button id="tour-prev" type="button">Back</button>
        <button id="tour-next" class="primary" type="button">Next</button>
    </div>
</dialog>
</body>
</html>
