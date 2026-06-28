# Image de base : Node 20 (version légère "alpine")
FROM node:20-alpine

WORKDIR /app

# On copie d'abord package.json pour profiter du cache Docker :
# tant que les dépendances ne changent pas, cette étape n'est pas refaite
COPY package*.json ./

# --legacy-peer-deps : nécessaire car certaines dépendances React ont des
# conflits de versions de peer dependencies (même réglage qu'en local et en CI)
RUN npm install --legacy-peer-deps

# Puis on copie le reste du code source
COPY . .

EXPOSE 3000
CMD ["npm", "start"]
