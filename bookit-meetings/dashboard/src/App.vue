<template>
	<div class="bm-app">
		<h1>Bookit Meetings</h1>
	</div>
</template>

<style>
#bookit-meetings-app {
	position: fixed;
	top: var(--bookit-mobile-header-height, 4rem);
	left: 0;
	right: 0;
	bottom: 0;
	overflow-y: auto;
	background: var(--bookit-bg-main, #f9fafb);
	z-index: 1;
}

@media (min-width: 1024px) {
	#bookit-meetings-app {
		top: 0;
		left: var(--bookit-sidebar-width, 16rem);
	}
}
</style>

<style scoped>
.bm-app {
	color: var(--bookit-text-primary);
	padding: 24px;
}

</style>

