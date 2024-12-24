docker build -t api-def . && docker run -p 8089:80 -v ./app/:/var/www/html/app/ api-def
