<script setup>
const meetings = window.bookitMeetings ?? {}
</script>

<template>
	<div class="bm-app">
		<h1>Bookit Meetings</h1>
		<div class="bm-card">
			<dl class="bm-kv">
				<div class="bm-row">
					<dt>meetings_enabled</dt>
					<dd>{{ meetings.meetings_enabled }}</dd>
				</div>
				<div class="bm-row">
					<dt>meetings_platform</dt>
					<dd>{{ meetings.meetings_platform }}</dd>
				</div>
				<div class="bm-row">
					<dt>meetings_manual_url</dt>
					<dd>{{ meetings.meetings_manual_url }}</dd>
				</div>
			</dl>
		</div>
		<pre class="bm-debug">{{ meetings }}</pre>
	</div>
</template>

<style scoped>
.bm-app {
	color: var(--bookit-text-primary);
	padding: 24px;
}

.bm-card {
	background: var(--bookit-bg-card);
	border-radius: 12px;
	padding: 16px;
	margin-top: 12px;
}

.bm-kv {
	display: grid;
	gap: 10px;
	margin: 0;
}

.bm-row {
	display: grid;
	grid-template-columns: 180px 1fr;
	gap: 12px;
	align-items: baseline;
}

.bm-row dt {
	font-weight: 600;
}

.bm-row dd {
	margin: 0;
	word-break: break-word;
}

.bm-debug {
	margin-top: 16px;
}
</style>

