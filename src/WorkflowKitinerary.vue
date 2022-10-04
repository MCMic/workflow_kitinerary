<template>
	<NcMultiselect
		v-model="currentValue"
		:options="values"
		track-by="id"
		:internal-search="true"
		label="text"
		@input="onInput" />
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
			return Object.keys(this.options).map(id => {
				return {
					id,
					text: this.options[id],
				}
			})
		},
	},
	methods: {
		onInput(newValue) {
			// when clicking on the an already selected item, we get null
			// this avoids unselecting an item
			if (newValue !== null) {
				this.$emit('input', newValue.id)
			}
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
