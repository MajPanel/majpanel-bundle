const { startStimulusApp } = require('@symfony/stimulus-bridge');
const { registerReactControllerComponents } = require('@symfony/ux-react');

const app = startStimulusApp(
    require.context('./controllers', true, /\.(j|t)sx?$/),
);

registerReactControllerComponents(
    require.context('./react/controllers', true, /\.tsx?$/),
);

module.exports = { app };
