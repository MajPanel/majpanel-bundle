import Encore from '@symfony/webpack-encore';

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/bundles/majpanel/build')
    .setManifestKeyPrefix('bundles/majpanel/build/')
    .addEntry('app', './assets/app.ts')
    .splitEntryChunks()
    .enableReactPreset()
    .enableStimulusBridge('./assets/controllers.json')
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enablePostCssLoader()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabel((config) => {
        config.plugins.push([
            'polyfill-corejs3',
            { method: 'usage-global', version: '3.49' },
        ]);
    })
    .enableTypeScriptLoader()
;

export default await Encore.getWebpackConfig();
