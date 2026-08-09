import { startStimulusApp } from '@symfony/stimulus-bridge';
import { registerReactControllerComponents } from '@symfony/ux-react';

const app = startStimulusApp(
    require.context('./controllers', true, /\.(j|t)sx?$/),
);

registerReactControllerComponents(
    require.context('./react/controllers', true, /\.tsx?$/),
);

export { app };
