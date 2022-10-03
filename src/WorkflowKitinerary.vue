<template>
	<NcMultiselect
		v-model="currentValue"
		:options="values"
		track-by="id"
		label="text"
		@input="(newValue) => newValue !== null && $emit('input', newValue.id)" />
</template>

<script>
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'WorkflowKitinerary',
	components: { NcMultiselect },
	props: {
		value: {
			default: '',
			type: String,
		},
	},
	data() {
		return {
			options: loadState('workflow_kitinerary', 'userCalendars'),
			currentValue: null,
		}
	},
	computed: {
		values() {
			return Object.keys(this.options).foreach(id => {
				return {
					id,
					text: this.options[id],
				}
			})
		},
	},
}
</script>

<style scoped>
	.multiselect {
		width: 100%;
		margin: auto;
		text-align: center;
	}
</style>
