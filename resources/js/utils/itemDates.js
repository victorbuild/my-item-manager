import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc.js'
import timezone from 'dayjs/plugin/timezone.js'

dayjs.extend(utc)
dayjs.extend(timezone)

export const getTodayDate = () => dayjs().tz('Asia/Taipei').format('YYYY-MM-DD')

export const getMaxItemDate = () => dayjs(getTodayDate()).add(1, 'month').format('YYYY-MM-DD')

const dateFields = ['purchased_at', 'received_at', 'used_at', 'discarded_at']

export const getDateMin = (dates, field) => {
    const earlier = dateFields.slice(0, dateFields.indexOf(field)).map(key => dates[key]?.slice(0, 10)).filter(Boolean)
    return earlier.sort().at(-1)
}

export const getDateMax = (dates, field) => {
    const later = dateFields.slice(dateFields.indexOf(field) + 1).map(key => dates[key]?.slice(0, 10)).filter(Boolean)
    return [getMaxItemDate(), ...later].sort()[0]
}
