import wrap from '@vue/web-component-wrapper'
import Vue from 'vue'

import WorkflowKitinerary from './WorkflowKitinerary.vue'

const FlowKItineraryComponent = wrap(Vue, WorkflowKitinerary)
const customElementId = 'oca-workflow_kitinerary-operation-import'

window.customElements.define(customElementId, FlowKItineraryComponent)

// In Vue 2, wrap doesn't support disabling shadow :(
// Disable with a hack
Object.defineProperty(FlowKItineraryComponent.prototype, 'attachShadow', { value() { return this } })
Object.defineProperty(FlowKItineraryComponent.prototype, 'shadowRoot', { get() { return this } })

OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\WorkflowKitinerary\\Operation',
	operation: '',
	element: customElementId,
	color: '#4d4d4d',
})
