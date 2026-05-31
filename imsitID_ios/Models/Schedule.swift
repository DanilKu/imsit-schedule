//
//  Schedule.swift
//  imsitID
//

import Foundation

struct Schedule: Codable {
    var week1: [Int: [Lesson]] = [:]
    var week2: [Int: [Lesson]] = [:]
    
    enum CodingKeys: String, CodingKey {
        case week1
        case week2
    }
    
    init() {}
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        if let week1Data = try? container.decode([String: [Lesson]].self, forKey: .week1) {
            for (key, value) in week1Data {
                if let day = Int(key) {
                    self.week1[day] = value
                }
            }
        }
        
        if let week2Data = try? container.decode([String: [Lesson]].self, forKey: .week2) {
            for (key, value) in week2Data {
                if let day = Int(key) {
                    self.week2[day] = value
                }
            }
        }
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        
        let week1Dict = Dictionary(uniqueKeysWithValues: week1.map { (String($0.key), $0.value) })
        let week2Dict = Dictionary(uniqueKeysWithValues: week2.map { (String($0.key), $0.value) })
        
        try container.encode(week1Dict, forKey: .week1)
        try container.encode(week2Dict, forKey: .week2)
    }
    
    func getLessons(week: Int, day: Int) -> [Lesson] {
        let weekSchedule = week == 1 ? week1 : week2
        return weekSchedule[day] ?? []
    }
    
    mutating func setLessons(week: Int, day: Int, lessons: [Lesson]) {
        if week == 1 {
            week1[day] = lessons
        } else {
            week2[day] = lessons
        }
    }
}

