FROM node:12.2-alpine as react_build
#also say
WORKDIR /apps/client
#copy the react app to the container
COPY ./client /apps/client

# #prepare the contiainer for building react
RUN npm install --silent
RUN npm install react-scripts@3.0.1 -g --silent
RUN yarn build

#prepare nginx
FROM nginx:1.16-alpine

COPY --from=react_build /apps/client/build /usr/share/nginx/html
#RUN rm /etc/nginx/conf.d/default.conf
#COPY ./docker/nginx/nginx.conf /etc/nginx/conf.d

#fire up nginx
EXPOSE 80
CMD ["nginx","-g","daemon off;"]