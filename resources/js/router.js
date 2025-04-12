import {createRouter, createWebHistory} from 'vue-router'
import Home from './Pages/Home.vue'
import ItemManager from './Pages/ItemManager.vue'
import CreateItem from './Pages/CreateItem.vue'
import ItemView from './Pages/ItemView.vue'
import EditItem from './Pages/EditItem.vue'
import NotFound from './Pages/NotFound.vue'

const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home
    },
    {
        path: '/items',
        name: 'ItemList',
        component: ItemManager
    },
    {
        path: '/items/create',
        name: 'ItemCreate',
        component: CreateItem
    },
    {
        path: '/items/:id',
        name: 'ItemView',
        component: ItemView
    },
    {
        path: '/items/:id/edit',
        name: 'ItemEdit',
        component: EditItem
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: NotFound
    }
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
