const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = webpackConfig

webpackConfig.entry = {
	flow: { import: path.join(__dirname, 'src', 'flow.js'), filename: 'workflow_kitinerary-flow.js'}
}
