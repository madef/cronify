Cronify
=======

Cronify help to manage and run cron tasks for php web applications 


Description
-----------

Cronify is composed of three php applications (standalone, no Apache or Nginx) :
 * Data server: manages the tasks list
 * Buffer server: an interface to get/set/put/delete tasks
 * Cron application: executes the tasks

There are two socket addresses:
 * To communicate with the buffer (127.0.0.1:8001)
 * To communicate with the data (127.0.0.1:8000)

Every communication must use a buffer, because the buffer is multi-connection, when the data is not. So only one connection a the time is supported by the data server.

```
   [web app]   [telnet]  [cronify]
       |          |          |
   [buffer1]  [buffer2]  [buffer3] ...
          \       |       /
           \      |      /
            \     |     /
             \    |    /
              \   |   /
               \  |  /
                \ | /
                [data]----[hard storage]
```

Usage
-----

Start the servers :

```
# ./run.sh
```

Connect to the buffer:
```
$ telnet 127.0.0.1 8001
```

