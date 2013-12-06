var io = require('socket.io').listen(8080);

io.sockets.on('connection', function (socket) {
    socket.on('message', function () { });
    socket.emit('news', { hello: 'world' });
    socket.on('ROR', function (data) {
        console.log(data);
    });
});