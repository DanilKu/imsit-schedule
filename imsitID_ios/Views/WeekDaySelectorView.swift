//
//  WeekDaySelectorView.swift
//  imsitID
//

import SwiftUI

struct WeekDaySelectorView: View {
    @EnvironmentObject var appState: AppState
    
    let dayNames = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб"]
    let fullDayNames = ["Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота"]
    
    var body: some View {
        VStack(spacing: 12) {
            // Week selector
            HStack(spacing: 0) {
                WeekButton(week: 1, isSelected: appState.currentWeek == 1) {
                    appState.switchWeek(1)
                }
                WeekButton(week: 2, isSelected: appState.currentWeek == 2) {
                    appState.switchWeek(2)
                }
            }
            .glassEffect(cornerRadius: 12)
            .padding(.horizontal, 4)
            .padding(.vertical, 4)
            
            // Day selector
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(1...6, id: \.self) { day in
                        DayButton(
                            day: day,
                            name: dayNames[day - 1],
                            isSelected: appState.currentDay == day
                        ) {
                            appState.switchDay(day)
                        }
                    }
                }
                .padding(.horizontal, 4)
            }
        }
    }
}

struct WeekButton: View {
    let week: Int
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            Text("\(week) неделя")
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(isSelected ? Color(red: 0.376, green: 0.510, blue: 0.980) : .white.opacity(0.7))
                .frame(maxWidth: .infinity)
                .padding(.vertical, 10)
                .background(
                    Group {
                        if isSelected {
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.2)
                        } else {
                            Color.clear
                        }
                    }
                )
                .cornerRadius(10)
        }
    }
}

struct DayButton: View {
    let day: Int
    let name: String
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            Text(name)
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(isSelected ? Color(red: 0.376, green: 0.510, blue: 0.980) : .white.opacity(0.7))
                .padding(.horizontal, 16)
                .padding(.vertical, 10)
                .background(
                    Group {
                        if isSelected {
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.2)
                        } else {
                            Color.white.opacity(0.05)
                        }
                    }
                )
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(
                            isSelected ? Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.3) : Color.white.opacity(0.1),
                            lineWidth: 1
                        )
                )
        }
    }
}

