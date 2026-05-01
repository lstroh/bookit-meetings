import { createApp } from 'vue'
import App from './App.vue'

const path = window.location.pathname
const isMeetingsRoute = path.includes( '/bookit-dashboard/app/meetings' )

if ( isMeetingsRoute ) {
	const el = document.getElementById( 'bookit-meetings-app' )
	if ( el ) {
		createApp( App ).mount( el )
	}
}

