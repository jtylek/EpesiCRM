module.exports = {
    context: __dirname + "/src",
    entry: "./index",
    devtool: 'source-map',
    output: {
        path: __dirname + "/dist",
        filename: "index.js"
    },
    module: {
        loaders: [
            {test: /bootstrap\/js\//, loader: 'imports?jQuery=jquery' },
            {test: /\.less$/, loader: 'style-loader!css-loader!less-loader'},
            {test: /\.css$/, loader: 'style-loader!css-loader'},
            {test: /\.woff(2)?(\?v=\d+\.\d+\.\d+)?$/, loader: "url-loader?limit=10000&mimetype=application/font-woff" },
            {test: /\.ttf(\?v=\d+\.\d+\.\d+)?$/, loader: "url-loader?limit=10000&mimetype=application/octet-stream" },
            {test: /\.eot(\?v=\d+\.\d+\.\d+)?$/, loader: "file-loader" },
            {test: /\.svg(\?v=\d+\.\d+\.\d+)?$/, loader: "url-loader?limit=10000&mimetype=image/svg+xml" },
            {test: /\.(png|jpg|gif)$/, loader: "file-loader" },
            {
                test: /\.js$/,
                exclude: /(node_modules|bower_components|libs)/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['env'],
                        plugins: ["transform-class-properties"]
                    }
                }
            }
        ]
    }
};