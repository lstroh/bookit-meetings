import { createApp } from 'vue'
import App from './App.vue'
import router from './router/index.js'

const el = document.getElementById( 'bookit-meetings-app' )
if ( el ) {
	const isMeetingsPage = window.location.pathname.includes( '/bookit-dashboard/app/meetings' )

	if ( ! isMeetingsPage ) {
		// Clear hash before router initialises so it never resolves to /meetings
		if ( window.location.hash ) {
			history.replaceState( null, '', window.location.pathname + window.location.search )
		}
	}

	createApp( App ).use( router ).mount( el )
}

