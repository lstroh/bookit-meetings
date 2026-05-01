import { createApp } from 'vue'
import App from './App.vue'
import router from './router/index.js'

const path = window.location.pathname
const isMeetingsRoute = path.includes( '/bookit-dashboard/app/meetings' )

if ( isMeetingsRoute ) {
	const el = document.getElementById( 'bookit-meetings-app' )
	if ( el ) {
		createApp( App ).use( router ).mount( el )
	}
}

