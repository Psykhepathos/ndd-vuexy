import appsAndPages from './apps-and-pages'
import charts from './charts'
import dashboard from './dashboard'
import forms from './forms'
import ndd from './ndd'
import others from './others'
import uiElements from './ui-elements'
import type { VerticalNavItems } from '@layouts/types'

export default [...ndd, ...dashboard, ...appsAndPages, ...uiElements, ...forms, ...charts, ...others] as VerticalNavItems
