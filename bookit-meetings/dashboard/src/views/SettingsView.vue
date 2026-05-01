<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import ToggleSwitch from '../components/ToggleSwitch.vue'
import PlatformSelector from '../components/PlatformSelector.vue'
import ManualUrlField from '../components/ManualUrlField.vue'

const loading = ref( true )
const saving = ref( false )
const loadError = ref( '' )
const status = reactive( { type: '', message: '' } )
let statusTimer = null

const form = reactive( {
	meetingsEnabled: false,
	meetingsPlatform: '',
	meetingsManualUrl: '',
} )

const platformOptions = [
	{ value: 'whatsapp', label: 'WhatsApp', disabled: false, comingSoon: false },
	{ value: 'teams', label: 'Microsoft Teams', disabled: false, comingSoon: false },
	{ value: 'generic', label: 'Generic URL', disabled: false, comingSoon: false },
	{ value: 'zoom', label: 'Zoom', disabled: true, comingSoon: true },
	{ value: 'google_meet', label: 'Google Meet', disabled: true, comingSoon: true },
]

const showPlatformSelector = computed( () => form.meetingsEnabled )
const showWhatsAppInfo = computed( () => form.meetingsEnabled && form.meetingsPlatform === 'whatsapp' )
const showManualUrl = computed(
	() => form.meetingsEnabled && ( form.meetingsPlatform === 'teams' || form.meetingsPlatform === 'generic' )
)

function setStatus( type, message, { autoDismissMs = 0 } = {} ) {
	status.type = type
	status.message = message
	if ( statusTimer ) window.clearTimeout( statusTimer )
	statusTimer = null
	if ( autoDismissMs > 0 ) {
		statusTimer = window.setTimeout( () => {
			status.type = ''
			status.message = ''
			statusTimer = null
		}, autoDismissMs )
	}
}

function getSettingsUrl() {
	const base = window?.BOOKIT_DASHBOARD?.restBase
	if ( typeof base === 'string' && base.length > 0 ) {
		return base.replace( 'bookit/v1/', '' ) + 'bookit-meetings/v1/settings'
	}
	return window.location.origin + '/wp-json/bookit-meetings/v1/settings'
}

function getHeaders() {
	return {
		'X-WP-Nonce': window.BOOKIT_DASHBOARD.nonce,
		'Content-Type': 'application/json',
	}
}

async function parseErrorMessage( res ) {
	try {
		const json = await res.json()
		if ( json?.message ) return String( json.message )
		if ( json?.data?.message ) return String( json.data.message )
	} catch {
		// ignore
	}
	return `Request failed (${res.status})`
}

async function loadSettings() {
	loading.value = true
	loadError.value = ''

	try {
		const res = await fetch( getSettingsUrl(), {
			method: 'GET',
			headers: getHeaders(),
		} )

		if ( ! res.ok ) {
			loadError.value = await parseErrorMessage( res )
			return
		}

		const json = await res.json()
		const data = json?.data || {}

		form.meetingsEnabled = String( data.meetings_enabled ?? '0' ) === '1'
		form.meetingsPlatform = String( data.meetings_platform ?? '' )
		form.meetingsManualUrl = String( data.meetings_manual_url ?? '' )
	} catch ( e ) {
		loadError.value = e?.message ? String( e.message ) : 'Failed to load settings.'
	} finally {
		loading.value = false
	}
}

async function save() {
	saving.value = true
	setStatus( '', '' )

	try {
		const body = {
			meetings_enabled: form.meetingsEnabled ? '1' : '0',
			meetings_platform: String( form.meetingsPlatform ?? '' ),
			meetings_manual_url: String( form.meetingsManualUrl ?? '' ),
		}

		const res = await fetch( getSettingsUrl(), {
			method: 'POST',
			headers: getHeaders(),
			body: JSON.stringify( body ),
		} )

		if ( ! res.ok ) {
			setStatus( 'error', await parseErrorMessage( res ) )
			return
		}

		const json = await res.json()
		const data = json?.data || {}

		form.meetingsEnabled = String( data.meetings_enabled ?? body.meetings_enabled ) === '1'
		form.meetingsPlatform = String( data.meetings_platform ?? body.meetings_platform )
		form.meetingsManualUrl = String( data.meetings_manual_url ?? body.meetings_manual_url )

		setStatus( 'success', 'Settings saved', { autoDismissMs: 3000 } )
	} catch ( e ) {
		setStatus( 'error', e?.message ? String( e.message ) : 'Failed to save settings.' )
	} finally {
		saving.value = false
	}
}

onMounted( () => {
	loadSettings()
} )

onUnmounted( () => {
	if ( statusTimer ) window.clearTimeout( statusTimer )
	statusTimer = null
} )
</script>

<template>
	<div class="bm-page">
		<h1 class="bm-page__title">Meetings Settings</h1>

		<div class="bm-card">
			<div v-if="loading" class="bm-state">Loading settings…</div>
			<div v-else-if="loadError" class="bm-state bm-state--error">
				{{ loadError }}
				<button type="button" class="bm-link" @click="loadSettings">Retry</button>
			</div>
			<div v-else class="bm-form">
				<ToggleSwitch
					v-model="form.meetingsEnabled"
					label="Enable Meetings"
					description="Allow online meeting links to be generated for bookings"
				/>

				<div v-if="showPlatformSelector" class="bm-section">
					<div class="bm-section__title">Platform</div>
					<PlatformSelector v-model="form.meetingsPlatform" :options="platformOptions" />
				</div>

				<div v-if="showWhatsAppInfo" class="bm-info">
					When WhatsApp is selected, no meeting link is generated. Your staff will initiate a WhatsApp call
					or video at the appointment time using the customer's phone number.
				</div>

				<div v-if="showManualUrl" class="bm-section">
					<ManualUrlField
						v-model="form.meetingsManualUrl"
						label="Meeting URL"
						placeholder="https://teams.microsoft.com/meet/your-meeting-id"
					/>
				</div>

				<div class="bm-actions">
					<button type="button" class="bm-button" :disabled="saving" @click="save">
						{{ saving ? 'Saving…' : 'Save' }}
					</button>
				</div>

				<div v-if="status.message" class="bm-status" :class="status.type === 'error' ? 'bm-status--error' : 'bm-status--success'">
					{{ status.message }}
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.bm-page__title {
	margin: 0 0 16px;
	color: var(--bookit-text-primary);
}

.bm-card {
	background: var(--bookit-bg-card);
	border: 1px solid var(--bookit-border-color);
	border-radius: 14px;
	padding: 16px;
}

.bm-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.bm-section__title {
	margin-bottom: 10px;
	color: var(--bookit-text-primary);
	font-weight: 600;
}

.bm-info {
	padding: 12px 14px;
	border-radius: 12px;
	border: 1px solid var(--bookit-border-color);
	background: var(--bookit-bg-card);
	color: var(--bookit-text-secondary);
	line-height: 1.4;
}

.bm-actions {
	display: flex;
	justify-content: flex-end;
}

.bm-button {
	background: var(--bookit-color-primary);
	border: 1px solid var(--bookit-color-primary);
	color: var(--bookit-text-primary);
	border-radius: 12px;
	padding: 10px 16px;
	cursor: pointer;
	transition: opacity 150ms ease;
}

.bm-button:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.bm-status {
	padding: 10px 12px;
	border-radius: 12px;
	border: 1px solid var(--bookit-border-color);
	color: var(--bookit-text-secondary);
}

.bm-status--success {
	border-color: var(--bookit-border-color);
}

.bm-status--error {
	border-color: var(--bookit-border-color);
}

.bm-state {
	color: var(--bookit-text-secondary);
}

.bm-state--error {
	color: var(--bookit-text-secondary);
}

.bm-link {
	margin-left: 10px;
	background: transparent;
	border: none;
	color: var(--bookit-text-primary);
	cursor: pointer;
	text-decoration: underline;
}
</style>

