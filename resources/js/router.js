import { createRouter, createWebHistory } from 'vue-router'
import Home from './Pages/Home.vue'
import ItemList from './Pages/Items/List.vue'
import ItemCreate from './Pages/Items/Create.vue'
import ItemShow from './Pages/Items/Show.vue'
import ItemEdit from './Pages/Items/Edit.vue'
import NotFound from './Pages/NotFound.vue'
import Login from './Pages/LoginView.vue'
import Register from './Pages/RegisterView.vue'
import ProductList from './Pages/Products/List.vue'
import ProductForm from './Pages/Products/Form.vue'
import ProductShow from './Pages/Products/Show.vue'
import DiscardedItemList from './Pages/DiscardedItems/List.vue'
import ExpiringItemList from './Pages/ExpiringItems/List.vue'
import Statistics from './Pages/Statistics.vue'
import CategoryList from './Pages/Categories/List.vue'
import CategoryForm from './Pages/Categories/Form.vue'
import CategoryShow from './Pages/Categories/Show.vue'

const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home
    },
    {
        path: '/login',
        name: 'Login',
        component: Login
    },
    {
        path: '/register',
        name: 'Register',
        component: Register
    },
    {
        path: '/products',
        name: 'ProductList',
        component: ProductList
    },
    {
        path: '/products/create',
        name: 'ProductCreate',
        component: ProductForm
    },
    {
        path: '/products/:id',
        name: 'ProductView',
        component: ProductShow
    },
    {
        path: '/products/:id/edit',
        name: 'ProductEdit',
        component: ProductForm
    },
    {
        path: '/items',
        name: 'ItemList',
        component: ItemList
    },
    {
        path: '/items/create',
        name: 'ItemCreate',
        component: ItemCreate
    },
    {
        path: '/items/:id',
        name: 'ItemView',
        component: ItemShow
    },
    {
        path: '/items/:id/edit',
        name: 'ItemEdit',
        component: ItemEdit
    },
    {
        path: '/discarded',
        name: 'DiscardedItems',
        component: DiscardedItemList
    },
    {
        path: '/expiring-items',
        name: 'ExpiringItems',
        component: ExpiringItemList
    },
    {
        path: '/statistics',
        name: 'Statistics',
        component: Statistics
    },
    {
        path: '/categories',
        name: 'CategoryList',
        component: CategoryList
    },
    {
        path: '/categories/create',
        name: 'CategoryCreate',
        component: CategoryForm
    },
    {
        path: '/categories/:id',
        name: 'CategoryView',
        component: CategoryShow
    },
    {
        path: '/categories/:id/edit',
        name: 'CategoryEdit',
        component: CategoryForm
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
    scrollBehavior(to, from, savedPosition) {
        return { left: 0, top: 0 }
    }
})
