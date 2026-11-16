<?php
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
<a class="skip" href="#main">Skip to content</a>
<header class="header">
    <div class="header-inner">
        <div class="brand"><span class="mark" aria-hidden="true">P</span><div><strong>Strathmore Smart Parking</strong><small>Parking &amp; access management</small></div></div>
        <nav id="navigation" aria-label="Main navigation" hidden>
            <button class="nav-button" data-screen="dashboard">Dashboard</button>
            <button class="nav-button" data-screen="gate" hidden>Gate operations</button>
            <button class="nav-button" data-screen="admin" hidden>Administration</button>
        </nav>
        <div class="header-actions">
            <button id="tour-button" class="quiet" type="button">Demo tour</button>
            <button id="logout" class="quiet" type="button" hidden>Sign out</button>
        </div>
    </div>
</header>
<main id="main" tabindex="-1">
    <p id="notice" class="notice" role="status" hidden></p>
    <section id="login-screen" class="screen login-layout" aria-labelledby="login-title" hidden>
        <div class="login-intro">
            <p class="eyebrow">Unofficial academic prototype</p>
            <h1 id="login-title">Campus parking</h1>
            <p>See available spaces by zone. Gate officers record vehicle arrivals and departures.</p>
        </div>
        <form id="login-form" class="panel login-form">
            <h2>Sign in</h2>
            <p class="muted">Use a fictional campus, security or administrator account.</p>
            <label>Email <input required type="email" name="email" autocomplete="username" maxlength="150" placeholder="student@parking.test"></label>
            <label>Password <input required type="password" name="password" autocomplete="current-password" maxlength="72"></label>
            <button type="submit" class="primary">Sign in</button>
            <details id="demo-credentials" hidden><summary>Local demo credentials</summary>
                <p>Password for each role <strong>ParkingDemo26!</strong></p>
                <div class="demo-picks">
                    <button type="button" data-demo="student">Student</button><button type="button" data-demo="staff">Staff</button>
                    <button type="button" data-demo="security">Security</button><button type="button" data-demo="admin">Admin</button>
                </div>
            </details>
        </form>
    </section>
    <section id="dashboard-screen" class="screen" aria-labelledby="dashboard-title" hidden>
        <div class="page-heading">
            <div><p class="eyebrow" id="identity"></p><h1 id="dashboard-title">Parking availability</h1>
                <p>First come, first served. Availability is based on vehicle movements recorded at the gate.</p></div>
            <small id="updated" class="pill"></small>
        </div>
        <div id="dashboard-stats" class="stats"></div>
        <div class="section-heading"><h2>Parking zones</h2><span class="muted">Fictional campus zones</span></div>
        <div id="dashboard-zones" class="zone-grid"></div>
    </section>
    <section id="gate-screen" class="screen" aria-labelledby="gate-title" hidden>
        <div class="page-heading"><div><p class="eyebrow">Security desk</p><h1 id="gate-title">Gate operations</h1><p>Record arrivals, departures and changes to a vehicle’s recorded zone.</p></div></div>
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
                <button type="submit" class="primary">Check in</button>
            </form>
            <section class="panel">
                <div class="section-heading"><h2>Active sessions</h2><button id="refresh-active" type="button">Refresh</button></div>
                <label>Search active sessions <input type="search" id="plate-search" maxlength="32" placeholder="Registration or session number"></label>
                <div id="active-list" class="record-list"></div>
            </section>
        </div>
    </section>
    <section id="admin-screen" class="screen" aria-labelledby="admin-title" hidden>
        <div class="page-heading"><div><p class="eyebrow">Administrator</p><h1 id="admin-title">Administration</h1><p>Manage zones and review parking activity.</p></div></div>
        <nav class="tabs" aria-label="Administration sections">
            <button data-admin="zones" aria-pressed="true">Zones</button>
            <button data-admin="report" aria-pressed="false">Reports</button>
            <button data-admin="audit" aria-pressed="false">Audit log</button>
            <button data-admin="accounts" aria-pressed="false">Accounts</button>
        </nav>
        <section class="panel" id="admin-zones">
            <div class="section-heading"><h2>Parking zones</h2><button id="add-zone" class="primary" type="button">Add zone</button></div>
            <div id="zone-list" class="record-list"></div>
        </section>
        <section class="panel" id="admin-report" hidden>
            <h2>Parking activity</h2><div id="report-stats" class="stats"></div>
            <h3>Zone usage</h3><div id="report-zones" class="zone-grid"></div>
            <h3>Session history</h3>
            <label>Find a session <input id="history-search" type="search" maxlength="32" placeholder="Registration or session number"></label>
            <div id="history-list" class="record-list"></div>
        </section>
        <section class="panel" id="admin-audit" hidden><h2>Audit log</h2><div id="audit-list" class="record-list"></div></section>
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
