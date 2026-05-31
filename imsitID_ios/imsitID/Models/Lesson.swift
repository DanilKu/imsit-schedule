//
//  Lesson.swift
//  imsitID
//

import Foundation

struct Lesson: Codable, Identifiable, Hashable {
    let id: UUID
    var subjectName: String
    var teacherName: String
    var roomNumber: String
    var startTime: String
    var endTime: String
    var lessonNumber: Int
    var dayOfWeek: Int
    var weekNumber: Int
    var groupName: String?
    var groups: [String]?
    
    enum CodingKeys: String, CodingKey {
        case subjectName = "subject_name"
        case teacherName = "teacher_name"
        case roomNumber = "room_number"
        case startTime = "start_time"
        case endTime = "end_time"
        case lessonNumber = "lesson_number"
        case dayOfWeek = "day_of_week"
        case weekNumber = "week_number"
        case groupName = "group_name"
        case groups
    }
    
    init(id: UUID = UUID(), subjectName: String, teacherName: String, roomNumber: String, startTime: String, endTime: String, lessonNumber: Int, dayOfWeek: Int, weekNumber: Int, groupName: String? = nil, groups: [String]? = nil) {
        self.id = id
        self.subjectName = subjectName
        self.teacherName = teacherName
        self.roomNumber = roomNumber
        self.startTime = startTime
        self.endTime = endTime
        self.lessonNumber = lessonNumber
        self.dayOfWeek = dayOfWeek
        self.weekNumber = weekNumber
        self.groupName = groupName
        self.groups = groups
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = UUID()
        
        // Более гибкое декодирование с обработкой ошибок
        subjectName = try container.decodeIfPresent(String.self, forKey: .subjectName) ?? ""
        teacherName = try container.decodeIfPresent(String.self, forKey: .teacherName) ?? ""
        roomNumber = try container.decodeIfPresent(String.self, forKey: .roomNumber) ?? ""
        
        // Время может быть в разных форматах
        if let startTimeStr = try? container.decode(String.self, forKey: .startTime) {
            startTime = startTimeStr
        } else {
            startTime = "08:00:00"
        }
        
        if let endTimeStr = try? container.decode(String.self, forKey: .endTime) {
            endTime = endTimeStr
        } else {
            endTime = "09:30:00"
        }
        
        lessonNumber = try container.decodeIfPresent(Int.self, forKey: .lessonNumber) ?? 0
        dayOfWeek = try container.decodeIfPresent(Int.self, forKey: .dayOfWeek) ?? 1
        weekNumber = try container.decodeIfPresent(Int.self, forKey: .weekNumber) ?? 1
        groupName = try container.decodeIfPresent(String.self, forKey: .groupName)
        groups = try container.decodeIfPresent([String].self, forKey: .groups)
    }
    
    var timeRange: String {
        let start = String(startTime.prefix(5))
        let end = String(endTime.prefix(5))
        return "\(start)–\(end)"
    }
    
    var progress: Double {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "HH:mm:ss"
        dateFormatter.timeZone = TimeZone(identifier: "Europe/Moscow")
        
        guard let start = dateFormatter.date(from: startTime),
              let end = dateFormatter.date(from: endTime),
              let now = dateFormatter.date(from: dateFormatter.string(from: Date())) else {
            return 0
        }
        
        let total = end.timeIntervalSince(start)
        let elapsed = now.timeIntervalSince(start)
        
        guard total > 0 else { return 0 }
        return max(0, min(1, elapsed / total))
    }
    
    var remainingTime: String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "HH:mm:ss"
        dateFormatter.timeZone = TimeZone(identifier: "Europe/Moscow")
        
        guard let end = dateFormatter.date(from: endTime) else {
            return "—"
        }
        
        let now = Date()
        let diff = end.timeIntervalSince(now)
        
        if diff <= 60 {
            return "меньше минуты"
        }
        
        let minutes = Int(ceil(diff / 60))
        let rounded = Int(ceil(Double(minutes) / 5.0) * 5.0)
        return "~\(rounded)м"
    }
}

