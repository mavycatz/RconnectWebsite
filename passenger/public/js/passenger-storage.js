/*
 * R-CONNECT passenger local storage registry.
 *
 * Single source of truth for every local key the passenger app persists, for
 * the reset routine used on logout / session invalidation / account switch /
 * the explicit "Clear cached app data" action, and for the keys a reset must
 * leave alone. Consumer pages load this file before their inline scripts:
 *   - passenger/views/layout/csrf.php (every passenger page)
 *   - the login success redirect pages (account switch)
 *
 * Keep this list in sync with passenger/docs/passenger-local-state.md.
 */
(function () {
    'use strict';

    if (window.PassengerStorage) {
        return;
    }

    var registry = {
        KEYS: {
            identityToken: 'remember_passenger',
            language: 'passenger_lang',
            homepageState: 'passenger_homepage_state',
            activeTripInfo: 'passenger_active_trip_info',
            continuationToken: 'rconnect_continuation_token',
            companionDismissed: 'rconnect_companion_dismissed',
            oneTimeTrip: 'rconnect_ot_trip',
            oneTimeTripHistory: 'rconnect_ot_trip_history',
            appInstalled: 'appInstalled',
            hideInstallPrompt: 'hideInstallPrompt'
        },

        /* Passenger-scoped keys removed by reset(); every writer must read
           its key from KEYS above so this list stays complete. */
        PASSENGER_KEYS: [
            'remember_passenger',
            'passenger_homepage_state',
            'passenger_active_trip_info',
            'rconnect_continuation_token',
            'rconnect_companion_dismissed',
            'rconnect_ot_trip',
            'rconnect_ot_trip_history',
            'passenger_cached_trip',
            'active_trip_info'
        ],

        /* Non-passenger keys that must survive a reset. */
        PREFERENCE_KEYS: [
            'passenger_lang',
            'appInstalled',
            'hideInstallPrompt'
        ],

        /* Session-scoped key prefixes removed by reset(). */
        PASSENGER_KEY_PREFIXES: [
            'ot_receipt_token_'
        ],

        reset: function () {
            try {
                sessionStorage.clear();
            } catch (e) {}

            try {
                var i;
                for (i = 0; i < this.PASSENGER_KEYS.length; i++) {
                    localStorage.removeItem(this.PASSENGER_KEYS[i]);
                }

                for (i = 0; i < this.PASSENGER_KEY_PREFIXES.length; i++) {
                    var prefix = this.PASSENGER_KEY_PREFIXES[i];
                    var doomed = [];

                    for (var j = 0; j < localStorage.length; j++) {
                        var key = localStorage.key(j);
                        if (key && key.indexOf(prefix) === 0) {
                            doomed.push(key);
                        }
                    }

                    for (j = 0; j < doomed.length; j++) {
                        localStorage.removeItem(doomed[j]);
                    }
                }
            } catch (e) {}
        }
    };

    window.PassengerStorage = registry;
})();
