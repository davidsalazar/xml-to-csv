const {app, BrowserWindow} = require('electron');

let window = null;

app.on('ready' , () => {
    window = new BrowserWindow({
        width : 600,
        height : 480,
        show : false
    })
    window.setMenu(null);
    window.loadURL(
        `file://${__dirname}/index.html`
    );
    window.once('ready-to-show' , () => {
        window.show();
        window.webContents.openDevTools();
    })
})

app.on('wnidow-all-closed',() => {
    app.quit();
})